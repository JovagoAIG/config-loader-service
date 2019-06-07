<?php
/******************************************************************************
 * Copyright (c) 2014 Valentijn Verhallen <contact@valentijn.co>              *
 *                                                                            *
 * Permission is hereby granted,  free of charge,  to any  person obtaining a *
 * copy of this software and associated documentation files (the "Software"), *
 * to deal in the Software without restriction,  including without limitation *
 * the rights to use,  copy, modify, merge, publish,  distribute, sublicense, *
 * and/or sell copies  of the  Software,  and to permit  persons to whom  the *
 * Software is furnished to do so, subject to the following conditions:       *
 *                                                                            *
 * The above copyright notice and this permission notice shall be included in *
 * all copies or substantial portions of the Software.                        *
 *                                                                            *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR *
 * IMPLIED, INCLUDING  BUT NOT  LIMITED TO THE WARRANTIES OF MERCHANTABILITY, *
 * FITNESS FOR A PARTICULAR  PURPOSE AND  NONINFRINGEMENT.  IN NO EVENT SHALL *
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER *
 * LIABILITY,  WHETHER IN AN ACTION OF CONTRACT,  TORT OR OTHERWISE,  ARISING *
 * FROM,  OUT OF  OR IN CONNECTION  WITH THE  SOFTWARE  OR THE  USE OR  OTHER *
 * DEALINGS IN THE SOFTWARE.                                                  *
 ******************************************************************************/

namespace Dehare\Symfony\ConfigLoaderService;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Yaml\Yaml;

class ConfigLoaderService
{
    /**
     * @var string  Current file
     */
    private $file;

    /**
     * @var string  Current file format
     */
    private $format;

    /**
     * @var string  Current parser function
     * @internal needs no registration; for integrality
     */
    private $parser;

    /**
     * @var srring  Current working dir
     */
    private $path;

    /**
     * @var array  Collect all importable configs
     */
    private $imports = array();

    /**
     * @var array  Collect computables
     */
    private $cache = array();

    /**
     * @var array   Configuration
     * Format: yml, yaml, json, php
     */
    private $config = array(
        'path'          => null,
        'format'        => 'yml',
        'valid_formats' => array('yml','yaml','php','json'),
    );


    /**
     * @param string  $path    Base path to configuration files
     * @param string  $format  Default format (= yml, yaml, json, php)
     */
    public function __construct($path, $format='yml')
    {
        $this->config['path']   = $path;
        $this->config['format'] = $this->validateFormat($format);
        $this->init();
    }

    public function __call($method, $args)
    {
        $methods = array('get');
        if (in_array($method, $methods) && method_exists($this, $method))
        {
            $this->init();
            $result = call_user_func_array(array($this, $method), $args);
            $this->cache = array(); // reset cache
            return $result;
        }
        else
        {
            throw new \Exception(sprintf("Could not initiate method [%s]", $method));
        }
    }

    /**
     * Load a configuration file and return contents
     *
     * @param  string $file   Filename (with or without extension)
     * @param  string $format The file format (defaults to autodetect) (= yml, yaml, json, php)
     * @param  string $path   Concatenation to $this->path
     * @return array          The configuration
     *
     * @example $ConfigLoaderService->get('config_file.yml');
     * @example $ConfigLoaderService->get('config_file', 'yml', '/path/configuration');
     *
     * @todo  recursive scan for resources
     */
    private function get($file, $format = null, $path=null)
    {
        $result = null;
        $this->format = $this->validateFormat($format);

        $this->setFile($file);
        $this->setPath($path);

        $checksum = md5($this->path.$this->file);
        if (!in_array($checksum, $this->cache['files']))
        {
            $this->cache['files'][] = $checksum;
        }
        else
        {
            // abort processing
            return;
        }

        $locator  = new FileLocator($this->path);
        $location = $locator->locate($this->file);
        $contents = file_get_contents($location);

        if (!$contents) {
            return;
        }

        $this->parser = str_replace('yml', 'yaml', $this->format);
        $config       = call_user_func_array(array($this, 'parse'.ucfirst($this->parser)), array($contents, $location));

        $this->validateConfig($config);
        $this->importResources($config);

        $result = $config ?: $result;
        return $result;
    }

    /**
     * laods yaml contents into array
     */
    private function parseYaml($contents)
    {
        $result = Yaml::parse($contents);
        return $result;
    }

    /**
     * loads json contents into array
     *
     * Beware to parse valid JSON data
     */
    private function parseJson($contents)
    {
        $result = json_decode($contents, true);
        if (!$result)
        {
            throw new \InvalidArgumentException(sprintf('The requested configuration "%s" could not be parsed: %s', $this->file, json_last_error()));
        }
        return $result;
    }

    /**
     * loads php contents into array
     *
     * Requirement: $contents must return valid array
     */
    private function parsePhp($contents, $file)
    {
        if (false == stripos($contents, 'return'))
        {
            throw new \InvalidArgumentException(sprintf('Requested configuration "%s" of type "PHP" must return array.', $$this->file));
        }

        $result = include $file;
        return $result;
    }

    /**
     * Runs through import directive and parses them into config
     *
     * @param array $config
     *
     * @example
     *   imports:
     *        - { resource: 'config1.yml' }
     *        - { resource: 'config2.yml' }
     *
     * @internal No need to loop $config, since there can only be one key 'imports'
     * @todo  add deep scan
     */
    private function importResources(&$config)
    {
        if (isset($config['imports']))
        {
            foreach($config['imports'] as $import)
            {
                if (isset($import['resource']))
                {
                    $file = $import['resource'];
                    $path = null;

                    $dn = dirname($file);
                    if (strlen($dn) > 1 && strpos($dn, '/') === 0)
                    {
                        $file = str_replace($dn.'/', '', $file);
                        $path = $dn;
                    }

                    $resource = $this->get($file, null, $path);
                    if ($resource)
                    {
                        $this->imports[] = $resource;
                    }
                }
            }
            unset($config['imports']);

            // be mindfull of overwriting; whereas config[x] overwrites import[x]
            foreach($this->imports as $import)
            {
                $config = array_replace_recursive($import, $config);
                array_shift($this->imports);
            }
        }
    }

    /**
     * Looks for file extension and sets final filename
     *
     * Loops through valid formats in search of current file type<br/>
     * and falls back into default format (config/request)
     *
     * Will strip invalid format requests
     *
     * @example
     *   $this->get('config.yml', 'php') => config.yml
     *
     * @param string $file
     */
    private function setFile($file)
    {
        $format    = $this->format; // fallback
        $extension = '.'.$format;

        // don't use strrpos, because files could have dots in their name
        foreach($this->config['valid_formats'] as $ext)
        {
            $ext_pos = strpos($file, '.'.$ext);
            if ($ext_pos !== false)
            {
                $format = strtolower(substr($file, $ext_pos+1));
                $extension = '';
                continue;
            }
        }

        $file .= $extension;
        $this->file = $file;
        $this->format = $format;
    }

    private function init()
    {
        $this->cache = array('files'   => array());
        $this->setPath();
    }

    /**
     * Validates given path
     * @param string $path  Deep scan dir path
     */
    private function setPath($path=null)
    {
        // default fallback && reset deep scan
        $this->path = $this->config['path'];

        if (!$path)
        {
            $this->validatePath();
        }
        // path !== basedir > concatenate
        elseif ($this->path != $path)
        {
            $this->path .= $path;
            $this->validatePath();
        }
    }

    /**
     * checks for path existence
     */
    private function validatePath()
    {
        $locator = new FileLocator();
        $locator->locate($this->path); // throws exception on failure
    }

    /**
     * checks whether requested format is allowed
     * @param  string $format
     * @return string          The used format
     */
    private function validateFormat($format=null)
    {
        $format = $format ?: $this->config['format'];
        if (!in_array($format, $this->config['valid_formats']))
        {
            throw new \InvalidArgumentException(sprintf('Invalid configuration format [%s]; use one of ("yml", "json", "php").', $format));
        }
        return $format;
    }

    /**
     * checks configuration contents
     */
    private function validateConfig($config)
    {
        if (is_null($config))
        {
            throw new \Exception(sprintf('The requested configuration "%s" could not be parsed', $this->file));
        }
        elseif (!is_array($config))
        {
            throw new \InvalidArgumentException(sprintf('Requested configuration "%s" of type "%s" does not return valid content.', $this->file, strtoupper($this->parser)));
        }
        return true;
    }

    public function getFile()
    {
        return $this->file;
    }
    public function getPath()
    {
        return $this->path;
    }
}