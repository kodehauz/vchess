<?php

namespace Drupal\vchess\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\vchess\Entity\Game;
use Drupal\vchess\Plugin\Block\CapturedPiecesBlock;
use Drupal\vchess\Plugin\Block\MoveListBlock;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AjaxController extends ControllerBase {

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer')
    );
  }

  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * Renders the movelist of the current game.
   *
   * @param \Drupal\vchess\Entity\Game $vchess_game
   *   The game to be rendered specified in the url.
   *
   * @return array|\Symfony\Component\HttpFoundation\JsonResponse
   */
  public function moveList(Game $vchess_game) {
    return MoveListBlock::buildContent($vchess_game);
  }

  /**
   * Renders the movelist of the specified game.
   *
   * @param \Drupal\vchess\Entity\Game $vchess_game
   *   The game to be rendered specified in the url.
   *
   * @return array|\Symfony\Component\HttpFoundation\JsonResponse
   */
  public function capturedPieces(Game $vchess_game) {
    return CapturedPiecesBlock::buildContent($vchess_game);
  }

}
