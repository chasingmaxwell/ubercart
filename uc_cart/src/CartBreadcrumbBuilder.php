<?php

/**
 * @file
 * Contains \Drupal\uc_cart\CartBreadcrumbBuilder.
 */

namespace Drupal\uc_cart;

use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Routing\LinkGeneratorTrait;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a custom breadcrumb builder for the cart page.
 */
class CartBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;
  use LinkGeneratorTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    return $route_match->getRouteName() == 'uc_cart.cart'
      && \Drupal::config('uc_cart.settings')->get('breadcrumb_text');
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $config = \Drupal::config('uc_cart.settings');
    $text = $config->get('breadcrumb_text');
    $breadcrumb[] = $this->l($this->t('Home'), '<front>');
    $breadcrumb[] = l($text, $config->get('breadcrumb_url'));

    return $breadcrumb;
  }

}
