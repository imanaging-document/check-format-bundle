ImanagingCheckFormatBundle
============

This bundle is a tool that allows you to check arrays format.

You have to provide a structure, which describe the format you want to check, and the datas you want to check. It can be simple array, or array of array.

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

Usage
----------------------------------
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

Examples
----------------------------------

Checking a line structure
```php
$structure = [
  // Type, Column Name, Nullable
  new FieldCheckFormat("string", "Name", false),
  new FieldCheckFormat("integer", "Age", true),
  new FieldCheckFormat("integer", "Height", true),
  new FieldCheckFormat("string", "City", true),
]

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
]

$lines = [
  ['Rémi', '26', '170', 'San Francisco'],
  ['Antonin', '26', '181', 'Miami'],
];
$result = CheckFormatFile::checkFormatFile($structure, $lines));

// In this case, error equals false and the errors list is empty because there is no error
// $result['error'] = false;
// $result['errors_list'] = [];
```