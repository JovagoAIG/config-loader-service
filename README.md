ConfigLoaderService
=========================

Another configuration service for Silex/Symfony supporting multiple file formats and imports.
Also supports cross-format loading.

[![Latest Stable Version](https://poser.pugx.org/dehare/config-loader-service/v/stable.png)](https://packagist.org/packages/dehare/config-loader-service)

# Installation

Add following line to your composer.json:

    "require": {
        [...]
        "dehare/config-loader-service": "1.1.*@dev"
        [...]
    }

--------------

# Usage

## Register as a service within Silex

Since this is a service, you should use the share method in any way you see fit.

```php
    use Dehare\Symfony\Config\ConfigLoaderService;

    $app['service.config'] = $app->share(function()
    {
        return new ConfigLoaderService('/dir/to/config', 'yml');
    });
```

## Register within Symfony

```yaml
services:
    service.config:
        class: Dehare\Symfony\Config\ConfigLoaderService
        arguments: [%kernel.root_dir%, %default_format%]
```

## Using the service

Now you can use it througout your application using:

```php
    // silex
    $app['service.config']->get('config_database');

    // symfony
    $this->container->get('service.config')->get('config_database');
```

Be sure to read the source for more hints on using the service.

## File formats

The service allows configurations in the following formats:
- JSON
- PHP
- YAML/YML

The default format used is YML.

The service is **forgiving** and will prefer the requested file extesion over the format.
See examples for detailed info.

## Imports

Symfony's Import-resource modal is also supported (in all formats).
Beware that the results will be merged. So be carefull when defining keys.

**You can supply different file formats within an import!**

# Examples

## YAML

```yaml
host: localhost
user: myuser
password: mypassword
```

## JSON
```json
{
    "variable1": "value",
    "array": {
        { "variable": "value" },
        { "variable": "value" }
    }
}
```

## PHP
```php
<?php
return array(
    'variable' => 'value',
    'array' => array(
        array('variable', 'variable1'),
    ),
)
```

## Imports (works with any format)
```yaml
imports:
    - { resource: 'file' }
    - { resource: 'file.php' }
    - { resource: 'file.json' }

variables:
    variable1: value
```

## Parsing configurations

**index.php** (Silex):

```php
    [...]
    $app = new Silex\Application();

    $app['service.config'] = $app->share(function()
    {
        return new ConfigLoaderService('/dir/to/config', 'yml');
    });
    [...]
```

**example.class.php**:

```php
    [...]
    $file = 'params1';
    $format = 'yaml';
    $dir = '/examples'; // => /dir/to/config/examples

    $app['service.config']->get($example_params, $format, $dir);
    [...]
```

## Forgiving

The following will search for config.yml rather than config.php / config.yml.php.
```php
$app['service.config']->get('config.yml', 'php');
```

# License
----------------
Copyright (c) 2014 Valentijn Verhallen <contact@valentijn.co>

Permission is hereby granted,  free of charge,  to any  person obtaining a
copy of this software and associated documentation files (the "Software"),
to deal in the Software without restriction,  including without limitation
the rights to use,  copy, modify, merge, publish,  distribute, sublicense,
and/or sell copies  of the  Software,  and to permit  persons to whom  the
Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING  BUT NOT  LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR  PURPOSE AND  NONINFRINGEMENT.  IN NO EVENT SHALL
THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY,  WHETHER IN AN ACTION OF CONTRACT,  TORT OR OTHERWISE,  ARISING
FROM,  OUT OF  OR IN CONNECTION  WITH THE  SOFTWARE  OR THE  USE OR  OTHER
DEALINGS IN THE SOFTWARE.
