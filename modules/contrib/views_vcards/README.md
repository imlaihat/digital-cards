Views Vcards
============

The Views vCards module allows the exporting of user fields in a vCard format.
This is mainly done because there was no way to export core user fields to a
vCard format without user intervention.

Requirements
------------
Drupal Core modules:
- Views

Composer dependencies:
- [maennchen/ZipStream-PHP](https://packagist.org/packages/maennchen/ZipStream-PHP)

Installation
------------

- Install the module with composer by running the following command:
  ```composer require 'drupal/views_vcards:^3.0'```. This ensures the right 
  version of the ZipStream library is installed as well. 
- Enable the module on Drupal's extensions page.

Configuration
-------------

- Create or edit a View showing users (or any other other entity) and create a
  new display of type 'vCard'. In the format section choose vCards for both 
  'Format' and 'Show'. The settings dialog for 'Show' will allow you to select 
  which field should be used for each vCard property.

    - Make sure the fields you want to use are added in the fields section,
      otherwise they will not show up.

- Views vCards offers the 'attach to' option under vCard settings, allowing
  this module to add a download link to another view. You can set up your user
  list as a normal view with all (exposed) filters if you require them, and the
  vCard export will adapt to the selected filters and provide the right vCards.
