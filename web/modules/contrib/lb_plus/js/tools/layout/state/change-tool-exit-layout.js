// Don't allow the change tool in nested layouts.
document.addEventListener('storeInitialized', e => {
  window.listenToStateChange(
    state => state.tool.currentTool,
    currentTool => {
      if (currentTool === 'edit_plus') {
        const exitNestedLayoutButton = document.getElementById('exit-nested-layout');
        if (exitNestedLayoutButton) {
          exitNestedLayoutButton.click();
        }
      }
    },
  );
});
