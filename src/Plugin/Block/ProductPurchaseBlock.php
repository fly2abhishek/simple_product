<?php

namespace Drupal\simple_product\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Endroid\QrCode\QrCode;
use Symfony\Component\DependencyInjection\ContainerInterface;

require_once \Drupal::service('module_handler')
    ->getModule('simple_product')
    ->getPath() . '/vendor/autoload.php';

/**
 * Provides a Block for product purchase QR code block.
 *
 * @Block(
 *  id = "product_purchase",
 *  admin_label = @Translation("Product Purchase Link Block"),
 *  context = {
 *    "node" = @ContextDefinition(
 *      "entity:node"
 *    )
 *  }
 * )
 */
class ProductPurchaseBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var routeMatch \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * @var route_match Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * ProductPurchaseBlock constructor.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, FileSystemInterface $file_system) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->fileSystem = $file_system;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('file_system')
    );
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = [];
    $config = $this->getConfiguration();
    $form['qr_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label that appears above the QA code.'),
      '#description' => $this->t('Enter a label'),
      '#default_value' => ($config['qr_text']) ? $config['qr_text'] : 'Scan the code',
    ];
    $form['size'] = [
      '#type' => 'number',
      '#title' => $this->t('Size of a QR code.'),
      '#description' => $this->t('Enter a number from 1-500'),
      '#default_value' => ($config['size']) ? $config['size'] : 300,
    ];
    $form['margin'] = [
      '#type' => 'number',
      '#title' => $this->t('Margin around the QR Code in Pixels.'),
      '#description' => $this->t('Enter a number from 1-50'),
      '#default_value' => ($config['margin']) ? $config['margin'] : 10,
    ];
    return $form;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['qr_text'] = $values['qr_text'];
    $this->configuration['size'] = $values['size'];
    $this->configuration['margin'] = $values['margin'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = $this->routeMatch->getParameter('node');

    if ($node instanceof NodeInterface && $node->getType() == 'product') {

      $filepath = $this->fileSystem->realpath(file_default_scheme() . "://");

      $path = '/qr-codes-' . $node->id() . '.png';
      if (!file_exists($filepath . $path)) {

        $link = $node->get('field_purchase_link')->uri;

        if ($link) {
          // Create a basic QR code
          $qrCode = new QrCode($link);
          $qrCode->setSize($this->configuration['size'])
            ->setMargin($this->configuration['margin']);

          $path = '/qr-codes-' . $node->id() . '.png';

          // Save it to a file
          $qrCode->writeFile($filepath . $path);
        }
        else {
          return [];
        }
      }

      if ($this->configuration['qr_text']) {
        $build['qr_text'] = [
          '#markup' => '<p>' . $this->configuration['qr_text'] . '</p>',
        ];
      }

      $build['qr_code'] = [
        '#theme' => 'image',
        '#uri' => file_default_scheme() . "://" . $path,
        '#alt' => $this->t('QR Code'),
      ];
      $build['#attached']['library'][] = 'simple_product/simple_product';
      return $build;

    }

  }

}
