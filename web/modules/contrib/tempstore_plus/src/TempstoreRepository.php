<?php

namespace Drupal\tempstore_plus;

use Drupal\tempstore_plus\Strategy\TempstoreStrategyInterface;

/**
 * Base class for tempstore repositories.
 *
 * Provides shared functionality for entity and layout tempstore repositories,
 * including delegating operations to appropriate strategies via the
 * StrategySelector service.
 */
abstract class TempstoreRepository {

  /**
   * Constructs a TempstoreRepository.
   *
   * @param \Drupal\tempstore_plus\StrategySelector $strategySelector
   *   The strategy selector service.
   */
  public function __construct(
    protected StrategySelector $strategySelector,
  ) {}

  /**
   * Gets the appropriate strategy for the subject.
   *
   * @param mixed $subject
   *   The subject (entity, section storage, etc.).
   *
   * @return \Drupal\tempstore_plus\Strategy\TempstoreStrategyInterface
   *   The strategy to use.
   */
  protected function getStrategy($subject): TempstoreStrategyInterface {
    return $this->strategySelector->getStrategy($subject);
  }

}
