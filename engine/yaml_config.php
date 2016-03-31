<?php

include('vendor/autoload.php');
if (!function_exists('yaml_parse_file') && !function_exists('spyc_load_file'))
  die("Requirment not met. At least one yaml tool is required. ".__FILE__." ".__LINE__);
if (!class_exists('\phpa2o\phpa2o'))
  die("Enelar/phpa2o is required for yaml config module");

class yaml_config
{
  private $config = false;
  private $predefined = [];

  public function __construct($file = null)
  {
    $default =
    [
      'config_location' => './config.yaml',
      'secret_location' => './secret.yaml',
    ];

    if (is_null($file))
      $this->predefined = $default;
    else
      $this->predefined = ["file" => $file];
  }

  public function __invoke()
  {
    if (!$this->config)
      $this->config = $this->init();
    return $this->config;
  }

  private function init()
  {
    return $this->load($this->predefined);
  }

  public function load($init)
  {
    $config = $init;

    foreach ($init as $k => $v)
    {
      $newdata = $this->load_file($init[$k]);
      $config = array_replace_recursive($config, $newdata);
    }

    $config = new \phpa2o\phpa2o($config);

    return $config;
  }

  private function load_file($file)
  {
    if (!file_exists($file))
      die("Failute at yaml config parse. '$file' doesnt exsist");
    if (function_exists('yaml_parse_file'))
      return yaml_parse_file($file);
    if (function_exists('spyc_load_file'))
      return spyc_load_file($file);
    die('Failure at yaml config parse. No tool available to reach this goal');
  }
}

if (!function_exists('conf'))
{
  $config = new yaml_config();
  function conf()
  {
    global $config;
    return $config();
  }
}
