<?php

namespace Drupal\osu_landscapeplants\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists the main-menu children of the current page as plain links.
 *
 * Replaces the D7 menu_block "Additional Information menu": an in-content
 * list of the pages filed under the current page's main-menu link. It is a
 * dedicated block rather than a second core main-menu block because the
 * theme dresses every rendering of the main menu as the top navigation bar.
 *
 * @Block(
 *   id = "osu_landscapeplants_section_menu",
 *   admin_label = @Translation("Section menu (children of the current page)"),
 *   category = @Translation("Landscape Plants")
 * )
 */
class SectionMenuBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The menu whose links are listed.
   */
  const MENU = 'main';

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected MenuLinkTreeInterface $menuTree,
    protected MenuActiveTrailInterface $activeTrail,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu.link_tree'),
      $container->get('menu.active_trail'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $active = $this->activeTrail->getActiveLink(self::MENU);
    if (!$active) {
      return [];
    }

    $parameters = (new MenuTreeParameters())
      ->setRoot($active->getPluginId())
      ->excludeRoot()
      ->setMaxDepth(1)
      ->onlyEnabledLinks();
    $tree = $this->menuTree->load(self::MENU, $parameters);
    $tree = $this->menuTree->transform($tree, [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);

    $items = [];
    foreach ($tree as $element) {
      if (!$element->access->isAllowed()) {
        continue;
      }
      $link = $element->link;
      $url = $link->getUrlObject();
      // D7 carried each link's description as its tooltip.
      if ($description = $link->getDescription()) {
        $url->setOption('attributes', ['title' => $description]);
      }
      $items[] = [
        '#type' => 'link',
        '#title' => $link->getTitle(),
        '#url' => $url,
      ];
    }
    if (!$items) {
      return [];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['class' => ['lp-section-menu']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route.menu_active_trails:' . self::MENU, 'user.permissions']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['config:system.menu.' . self::MENU]);
  }

}
