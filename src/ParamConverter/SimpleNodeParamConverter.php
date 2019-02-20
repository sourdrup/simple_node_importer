<?php

namespace Drupal\simple_node_importer\ParamConverter;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\Routing\Route;

class SimpleNodeParamConverter implements ParamConverterInterface {
  public function convert($value, $definition, $name, array $defaults) {
    return \Drupal::entityManager()->getStorage('node')->load($value);
  }

  public function applies($definition, $name, Route $route) {
    return (!empty($definition['type']) && $definition['type'] == 'node');
  }
}