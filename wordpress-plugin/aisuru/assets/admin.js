(() => {
  const copyButtons = document.querySelectorAll('.aisuru-copy');

  copyButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      const text = button.dataset.copy || '';
      if (!text) return;

      try {
        await navigator.clipboard.writeText(text);
        const previous = button.textContent;
        button.textContent = 'Copiato';
        window.setTimeout(() => {
          button.textContent = previous;
        }, 1400);
      } catch {
        window.prompt('Copia lo shortcode:', text);
      }
    });
  });

  document.querySelectorAll('[data-confirm]').forEach((link) => {
    link.addEventListener('click', (event) => {
      if (!window.confirm(link.dataset.confirm)) {
        event.preventDefault();
      }
    });
  });

  const layoutInputs = document.querySelectorAll('[data-layout-grid] input');
  const assistantOptions = document.querySelector(
    '[data-website-assistant-options]',
  );

  const updateAssistantOptions = () => {
    if (!assistantOptions) return;
    const selected = document.querySelector('[data-layout-grid] input:checked');
    assistantOptions.hidden =
      !selected || selected.value !== 'WEBSITE_ASSISTANT';
  };

  layoutInputs.forEach((input) => {
    input.addEventListener('change', updateAssistantOptions);
  });
  updateAssistantOptions();

  const syncButton = document.querySelector('[data-integration-sync]');
  const syncStatus = document.querySelector('[data-integration-status]');
  const editorForm = document.querySelector('form.aisuru-editor');

  const setSyncStatus = (message, state = '') => {
    if (!syncStatus) return;
    syncStatus.textContent = message;
    syncStatus.dataset.state = state;
  };

  const showInheritedValues = (config) => {
    Object.entries(config.options || {}).forEach(([name, value]) => {
      const hint = document.querySelector(`[data-inherited-option="${name}"]`);
      const select = document.querySelector(`[name="${name}"]`);
      const label = value
        ? window.aisuruAdmin.labels.on
        : window.aisuruAdmin.labels.off;

      if (hint) {
        hint.textContent = `${window.aisuruAdmin.labels.inherited}: ${label}`;
        hint.hidden = false;
      }
      if (select && select.options[0]) {
        select.options[0].textContent = `${window.aisuruAdmin.labels.inherit} (${label})`;
      }
    });

    [
      'contextVars',
      'initialQuestion',
      'maxTotalMessagePayload',
      'tag',
      'pin',
    ].forEach((key) => {
      const value = config[key];
      const field = document.querySelector(`[data-inherited-field="${key}"]`);
      const hint = document.querySelector(`[data-inherited-value="${key}"]`);
      if (value === '' || value == null || !field || !hint) return;

      if (!field.value) {
        field.placeholder = String(value);
      }
      hint.textContent = `${window.aisuruAdmin.labels.inherited}: ${value}`;
      hint.hidden = false;
    });

    const layoutHint = document.querySelector('[data-inherited-layout]');
    if (layoutHint && config.layout) {
      layoutHint.textContent = `${window.aisuruAdmin.labels.inherited} — Layout: ${config.layout}`;
      layoutHint.hidden = false;
    }
  };

  const syncIntegration = async () => {
    if (!syncButton || !editorForm || !window.aisuruAdmin) return;

    const formData = new FormData(editorForm);
    const required = [
      'tenant_id',
      'memori_name',
      'owner_user_name',
      'integration_id',
    ];
    if (required.some((field) => !formData.get(field))) {
      setSyncStatus(window.aisuruAdmin.labels.missing, 'error');
      return;
    }

    const payload = new URLSearchParams({
      action: 'aisuru_preview_integration',
      nonce: window.aisuruAdmin.nonce,
    });
    [...required, 'api_url'].forEach((field) => {
      payload.set(field, formData.get(field) || '');
    });

    syncButton.disabled = true;
    setSyncStatus(window.aisuruAdmin.labels.loading, 'loading');

    try {
      const response = await fetch(window.aisuruAdmin.ajaxURL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
        },
        body: payload.toString(),
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(
          result?.data?.message || window.aisuruAdmin.labels.error,
        );
      }

      showInheritedValues(result.data);
      setSyncStatus(window.aisuruAdmin.labels.loaded, 'success');
    } catch (error) {
      setSyncStatus(
        error instanceof Error
          ? error.message
          : window.aisuruAdmin.labels.error,
        'error',
      );
    } finally {
      syncButton.disabled = false;
    }
  };

  if (syncButton) {
    syncButton.addEventListener('click', syncIntegration);

    const integrationField = editorForm?.querySelector(
      '[name="integration_id"]',
    );
    integrationField?.addEventListener('change', syncIntegration);

    if (integrationField?.value) {
      syncIntegration();
    }
  }
})();
