(() => {
  const settings = window.aisuruLoader || {};
  let observer;

  const addStyle = (url, id, existingSelector = '') => {
    if (
      !url ||
      document.getElementById(id) ||
      (existingSelector && document.querySelector(existingSelector))
    ) {
      return;
    }

    const link = document.createElement('link');
    link.id = id;
    link.rel = 'stylesheet';
    link.href = url;
    document.head.appendChild(link);
  };

  const stopObserving = () => {
    observer?.disconnect();
  };

  const loadWidget = () => {
    if (!document.querySelector('memori-client')) return;

    addStyle(
      settings.widgetStyleURL,
      'aisuru-widget-css',
      'link[href*="memori-react"][href*="styles.min.css"]',
    );
    addStyle(settings.publicStyleURL, 'aisuru-public-css');

    if (customElements.get('memori-client')) {
      stopObserving();
      return;
    }

    const existingScript = document.querySelector(
      'script[src*="memori-webcomponent"]',
    );
    if (existingScript) {
      customElements.whenDefined('memori-client').then(stopObserving);
      return;
    }

    const script = document.createElement('script');
    script.id = 'aisuru-webcomponent-module';
    script.type = 'module';
    script.src = settings.scriptURL;
    script.addEventListener('load', () => {
      customElements.whenDefined('memori-client').then(stopObserving);
    });
    document.head.appendChild(script);
  };

  observer = new MutationObserver((records) => {
    const widgetAdded = records.some((record) =>
      [...record.addedNodes].some(
        (node) =>
          node.nodeType === Node.ELEMENT_NODE &&
          (node.matches('memori-client') ||
            node.querySelector('memori-client')),
      ),
    );
    if (widgetAdded) loadWidget();
  });
  observer.observe(document.documentElement, {
    childList: true,
    subtree: true,
  });
  loadWidget();
})();
