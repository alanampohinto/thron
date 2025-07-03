# THRON / Drupal Connector: installation procedure

INTRODUCTION
------------

The Drupal connector allows publishers to select the multimedia files stored
in the DAM directly from the CMS media library, relieving the website
architecture from the management of multimedia file distribution and taking
full advantage of all the features and potential of THRON's Elastic Media
Delivery. Integration brings the following benefits:

  * It minimizes integration costs with THRON: just configure the connector
  and the THRON contents will be immediately available in the CMS multimedia
  library.
  * It allows you to remove the duplication of media files between THRON and
  the Drupal media library: THRON is the only source of media files.
  * It improves page loading performance and peak management: the
  distribution of multimedia files is entirely managed by THRON Elastic Media
  Delivery and through the Universal Player.
  * It automatically collects content intelligence data: tracks any
  interaction between users and media files and profiles audience interests.

For a full description of the module, visit the project page: https://www.drupal.org/project/thron

To submit bug reports and feature suggestions, or track changes: https://www.drupal.org/project/issues/thron

REQUIREMENTS
------------

This module requires the following modules:

* Entity Browser (https://www.drupal.org/project/entity_browser)
* Entity Embed (https://www.drupal.org/project/entity_embed)
* Media Entity Browser (https://www.drupal.org/project/media_entity_browser)
* jQuery UI Autocomplete (https://www.drupal.org/project/jquery_ui_autocomplete)
* CKEditor 4 - WYSIWYG HTML editor (https://www.drupal.org/project/ckeditor)

INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. Visit
 https://www.drupal.org/docs/extending-drupal/installing-modules
 for further information.

CONFIGURATION
-------------

## Configuring Credentials and Integration details

Go to the `/admin/config/services/thron` path in the Drupal settings for
entering the credentials and configuring the integration details.

![THRON Application configuration](https://hub-cdn.thron.com/delivery/public/image/hub/99112ae9-7b21-4473-8444-f15334524936/0p5eek/std/1024x768/00-drupal-config "THRON Application configuration")

Fill the panel with the THRON credentials (client ID, app ID, app key) you
have been provided and then click on the "Save configuration" button to start
the integration process.

Once you have saved the credentials, you will be asked to provide a series
of further customizations: the most important are related to he intelligence
classifications that will be used to restrict the search on tags to; the
connector will search only on the tags on the enabled classifications
(for instance, "Topic" and "Target").

## Configuring text format

In the following configuration step you will enable the THRON content
embedding tool by adding the relevant button in the CKEditor 4 toolbar. You
can choose the text format you want to enable this button on (Basic HTML,
Full HTML, or any other text format defined in the Drupal site
configuration); for example, if you want to add the THRON plugin to the "Full
HTML" text format you will have to go to
`/admin/config/content/formats/manage/full_html` in your Drupal site. The URL
format for this configuration page is in the form
`/admin/config/content/formats/manage/{text format}`.

Drag the "THRON" button from the "Available buttons" to one suitable position
in the "Active toolbar".

![Configuring the text format](https://hub-cdn.thron.com/delivery/public/image/hub/8e38eb0c-f023-42fa-a32d-23cf12ad2365/ycswyb/std/1024x162/02-drupal-config "Configuring the text format")

![THRON button in its place](https://hub-cdn.thron.com/delivery/public/image/hub/c4e410a0-4789-4975-8f7a-43eccae34e9e/0dea8j/std/1024x768/025-drupal-config "THRON button in its place")

Make sure that the "Display embedded entities" checkbox is flagged and then
save the text format.

![Display embedded entities checkbox](https://hub-cdn.thron.com/delivery/public/image/hub/f2fb21d2-fe11-4c38-a263-736f5b175869/t3zxcd/std/922x455/03-drupal-config "Display embedded entities checkbox")

You will now be able to embed THRON content via the button in the CKEditor
window in the desired text format.

![Embed THRON content in page](https://hub-cdn.thron.com/delivery/public/image/hub/8f5cee6b-47bf-4f94-91c6-4c8c61b089a6/tyxv1l/std/1024x488/04-drupal-config "Embed THRON content in page")

For more detail please check [the Drupal Connector's page](https://marketplace.thron.com/EN/apps/drupal-connector)
in the [THRON Marketplace](https://marketplace.thron.com).


## Note: This module requires some dependencies that are only available as release candidates (RC). To install them via Composer, you must explicitly allow RC stability, for example:

on the composer.json file you should have these settings:
"minimum-stability": "RC",
"prefer-stable": true

And then you can install the package by doing:

composer require drupal/inline_entity_form:^3.0@RC

Additionally, in order to install the required dependencies for the Thron module in Drupal 11, it is necessary to use the `composer-drupal-lenient` plugin. This is because some dependencies have strict version constraints that are not fully compatible with Drupal 11 by default.

To proceed, run the following commands:

composer require mglaman/composer-drupal-lenient
composer config --merge --json extra.drupal-lenient.allowed-list '["drupal/thron", "drupal/media_entity_browser", "drupal/ckeditor", "drupal/media_entity_browser_media_library"]'