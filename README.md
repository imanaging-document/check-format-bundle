ImanagingCheckFormatBundle
============

This bundle is a tool that allows you to check arrays format.

You have to provide a structure, which describe the format you want to check, and the datas you want to check. It can be simple array, or array of array.

A mapping function is available inside this bundle, you have to implement some required interfaces to make it works. You will find more information about how to use it in this readme.

This bundle should be used inside an imanaging-document application.

Install the bundle with:

```console
$ composer require imanaging-document/check-format-bundle
```

Configuration
----------------------------------

You have to create a ```config/packages/imanaging_check_format.yaml``` file:
```yaml
imanaging_check_format:
    bar: ~
```

This configuration is optionnal.

Check Format
----------------------------------

### Usage
You can access two statics methods named ``checkFormatFile`` and ``getObjLine`` where you need it in your project juste like this :

```php
use Imanaging\CheckFormatBundle\CheckFormatFile;

class MyBeautifulService
{  
  /**
   * ...
   */
  public function myBeautifulFunction(...){
    ...
    $result = CheckFormatFile::checkFormatLine($structureDescription, $myLineToCheckFormat));
    ...
  }
  ...
}
```

### Examples

Checking a line structure
```php
$structure = [
  // Type, Column Name, Nullable
  new FieldCheckFormat("string", "Name", false),
  new FieldCheckFormat("integer", "Age", true),
  new FieldCheckFormat("integer", "Height", true),
  new FieldCheckFormat("string", "City", true),
];

$myLine = ['Rémi', '26', '170', 'San Francisco'];
$result = CheckFormatFile::checkFormatLine($structure, $myLine));

// In this case, error equals false and the errors list is empty because there is no error
// $result['error'] = false;
// $result['errors_list'] = [];
```

Checking a file structure
```php
$structure = [
  // Type, Column Name, Nullable
  new FieldCheckFormat("string", "Name", false),
  new FieldCheckFormat("integer", "Age", true),
  new FieldCheckFormat("integer", "Height", true),
  new FieldCheckFormat("string", "City", true),
];

$lines = [
  ['Rémi', '26', '170', 'San Francisco'],
  ['Antonin', '26', '181', 'Miami'],
];
$result = CheckFormatFile::checkFormatFile($structure, $lines));

// In this case, error equals false and the errors list is empty because there is no error
// $result['error'] = false;
// $result['errors_list'] = [];
```

Mapping
----------------------------------

#### Requirements

- Symfony 4
- JQuery
- Bootstrap 4
- FontAwesome

#### Configuration

#### Interfaces

You have to implements the following interfaces in your ```config/packages/doctrine.yaml``` file :
```yaml
Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationInterface: App\Entity\MappingConfiguration
    Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationTypeInterface: App\Entity\MappingConfigurationType
    Imanaging\CheckFormatBundle\Interfaces\MappingChampPossibleInterface: App\Entity\MappingChampPossible
    Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceFileInterface: App\Entity\MappingConfigurationValueAvanceFile
    Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceInterface: App\Entity\MappingConfigurationValueAvance
    Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceTextInterface: App\Entity\MappingConfigurationValueAvanceText
    Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceTypeInterface: App\Entity\MappingConfigurationValueAvanceType
    Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueInterface: App\Entity\MappingConfigurationValue
    Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueTransformationInterface: App\Entity\MappingConfigurationValueTransformation
    Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueTranslationInterface: App\Entity\MappingConfigurationValueTranslation
```

#### Usage and examples

You can call a mapping page with an ajax call :
```html
<div id="mappingDiv">
    <div class="text-center mt-5">
      <h4><i class="fa fa-spinner fa-spin fa-fw"></i>Chargement en cours ...</h4>
    </div>
</div>
```

```js
let data = {files_directory: '/public/database/attestation/fichier_client*.csv', next_step_route: 'hephaistos_administration_import_fichier_client',
  mapping_configuration_type: 'integration_client'};
$.ajax({
  url: "{{ path('check_format_mapping_page') }}",
  type: 'POST',
  data: data,
  success: function (data) {
    $("#mappingDiv").html(data);
  },
  error: function () {
    Toast.fire({type: 'error', title: 'Error'});
  }
});
```