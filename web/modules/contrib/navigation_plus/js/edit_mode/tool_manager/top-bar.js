(($, Drupal, once) => {

  /**
   * Refresh button.
   */
  Drupal.behaviors.NavigationPlusRefresh = {
    attach: (context, settings) => {
      once('NavigationPlusRefresh', '#navigation-plus-refresh', context).forEach(refreshButton => {
        refreshButton.onclick = (e) => {
          location.reload();
        }
      });
    }
  };

  /**
   * Save button
   */
  Drupal.behaviors.NavigationPlusSave = {
    attach: (context, settings) => {
      once('NavigationPlusSave', '#navigation-plus-save', context).forEach(saveButton => {

        saveButton.onclick = async (e) => {
          if (Drupal.EditPlus) {
            await Drupal.EditPlus.notUpdating();
          }
          // @todo Temporarily use the edit+ save controller.
          // @todo This creates a dependency on edit+ when in reality the
          // @todo tempstore for edit+ and LB+ should bubble up to some parent
          // @todo module.
          const info = Drupal.NavigationPlus.ModeManager.getPlugin('edit').getMainEntityInfo();
          if (!info) {
            return;
          }

          if (Drupal.EditPlus?.hasInvalidFormItem?.() === true) {
            // @todo warnAboutInvalidFormItem should detect that there is already a message on screen for this so you don't get
            // double messages (the form submit validation and the "hey fix this before you continue")
            Drupal.EditPlus.warnAboutInvalidFormItem();
            return;
          }

          window.location = Drupal.NavigationPlus.ModePluginBase.url('/edit-plus/tempstore/save/' + info.entityType + '.' + info.id + '?destination=' + window.location.pathname);
        }
      });
    }
  };

  /**
   * Discard changes button
   */
  Drupal.behaviors.NavigationPlusDiscardChanges = {
    attach: (context, settings) => {
      once('NavigationPlusDiscardChanges', '#navigation-plus-discard-changes', context).forEach(discardChangesButton => {

        discardChangesButton.onclick = (e) => {
          // @todo Temporarily use the edit+ delete controller.
          // @todo This creates a dependency on edit+ when in reality the
          // @todo tempstore for edit+ and LB+ should bubble up to some parent
          // @todo module.
          const info = Drupal.NavigationPlus.ModeManager.getPlugin('edit').getMainEntityInfo();
          if (!info) {
            return;
          }
          Drupal.NavigationPlus.ModePluginBase.ajax({
            url: '/edit-plus/tempstore/delete-confirm/' + info.entityType + '.' + info.id + '?destination=' + window.location.pathname,
            dialogType: 'modal',
            dialog: {
              title: Drupal.t('Discard changes?'),
              width: 'auto',
              height: 'auto',
              dialogClass: 'discard-changes-dialog',
            },
            error: error => {
              console.error('Unable to discard changes: ', error.responseText || error);
              Drupal.NavigationPlus.ModeManager.getPlugin('edit').handleError(error, 'Unable to discard changes.');
            },
          }).execute();
        }
      });
    }
  };

})(jQuery, Drupal, once);
