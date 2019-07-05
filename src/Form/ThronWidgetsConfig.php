<?php

namespace Drupal\thron\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_browser\Form\WidgetsConfig;

/**
 * Form for configuring widgets for entity browser.
 */
class ThronWidgetsConfig extends WidgetsConfig {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Let the parent class build the main form.
    $form_state->set('widget_uuid', FALSE);
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\entity_browser\EntityBrowserInterface $entity_browser */
    $entity_browser = $this->getEntity();

    /** @var \Drupal\entity_browser\WidgetInterface $widget */
    foreach ($entity_browser->getWidgets() as $uuid => $widget) {
      // Pass the widget UUID only to '‌thron_search' widgets and rebuild their config form.
      if (in_array($widget->getBaseId(), ['thron_search', 'thron_upload'])) {
        $form_state->set('widget_uuid', $uuid);
        $form['widgets']['table'][$uuid]['form'] = $widget->buildConfigurationForm([], $form_state);
      }
    }

    return $form;
  }

}
