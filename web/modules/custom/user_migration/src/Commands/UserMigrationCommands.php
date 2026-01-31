<?php

namespace Drupal\user_migration\Commands;

use Drupal\Core\Database\Connection;
use Drupal\user\Entity\User;
use Drupal\user\Entity\Role;
use Drush\Commands\DrushCommands;

/**
 * Provides Drush commands for user migration.
 */
class UserMigrationCommands extends DrushCommands {

  protected $connection;

  public function __construct(Connection $connection) {
    parent::__construct();
    $this->connection = $connection;
  }

  /**
   * Run migration of users, roles, and permissions.
   *
   * @command user_migration:run
   * @aliases umrun
   */
  public function runMigration() {
    $connection = $this->connection;

    // Truncate migration tables.
    $connection->truncate('migration_user_role_permission')->execute();
    $connection->truncate('migration_users')->execute();
    $connection->truncate('migration_roles')->execute();
    $connection->truncate('migration_user_roles')->execute();

    // --- Step 0: Populate migration_user_role_permission ---
    $uids = \Drupal::entityQuery('user')->accessCheck(FALSE)->execute();

    foreach ($uids as $uid) {
      $user = User::load($uid);
      if (!$user) {
        continue;
      }
      foreach ($user->getRoles() as $role_id) {
        $role_entity = Role::load($role_id);
        if (!$role_entity) {
          continue;
        }
        foreach ($role_entity->getPermissions() as $permission) {
          $connection->insert('migration_user_role_permission')
            ->fields([
              'user_id' => $uid,
              'role' => $role_id,
              'permission' => $permission,
            ])
            ->execute();
        }
      }
    }

    // --- Step 1: Populate migration_roles ---
    $roles = Role::loadMultiple();
    $role_map = [];
    foreach ($roles as $role_id => $role_entity) {
      $connection->insert('migration_roles')
        ->fields([
          'role_id' => $role_id,
          'role_name' => $role_entity->label(),
          'role_description' => '',
        ])
        ->execute();
      $role_map[$role_id] = $connection->lastInsertId();
    }

    // --- Step 2: Populate migration_users and migration_user_roles ---
    foreach ($uids as $uid) {
      $user = User::load($uid);
      if (!$user) {
        continue;
      }
      $cac_subject = $connection->select('jammex_header_login', 'jhl')
        ->fields('jhl', ['subject'])
        ->condition('jhl.uid', $uid)
        ->execute()
        ->fetchField() ?: '';

      $connection->insert('migration_users')
        ->fields([
          'user_name' => $user->getAccountName(),
          'pass' => $user->getPassword() ?? 'dummy',
          'email' => $user->getEmail() ?? $user->getAccountName() . '@notfound.com',
          'default_repo_id' => $user->hasField('field_cold_spray_branch_service') ? $user->get('field_cold_spray_branch_service')->value : '',
          'is_jammex_admin' => in_array('administrator', $user->getRoles()) ? 1 : 0,
          'is_active' => $user->isActive() ? 1 : 0,
          'first_name' => $user->hasField('field_name') ? $user->get('field_name')->value : '',
          'last_name' => $user->hasField('field_last_name') ? $user->get('field_last_name')->value : '',
          'cac_subject' => $cac_subject,
          'last_login_date' => $user->getLastLoginTime(),
          'created_date' => $user->getCreatedTime(),
          'last_updated_date' => $user->getChangedTime(),
        ])
        ->execute();

      foreach ($user->getRoles() as $role_id) {
        if (isset($role_map[$role_id])) {
          $connection->insert('migration_user_roles')
            ->fields([
              'user_id' => $uid,
              'role_id' => $role_id,
            ])
            ->execute();
        }
      }
    }

    $this->output()->writeln('User migration completed successfully.');
  }

}
