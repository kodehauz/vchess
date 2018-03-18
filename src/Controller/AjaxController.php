<?php

namespace Drupal\vchess\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\user\Entity\User;
use Drupal\vchess\Entity\Game;
use Drupal\vchess\Plugin\Block\CapturedPiecesBlock;
use Drupal\vchess\Plugin\Block\MoveListBlock;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

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

  /**
   * Updates the game timer for a user.
   */
  public function updateTimer(Request $request, Game $vchess_game) {
    $user = User::load($this->currentUser()->id());
    $timer = $request->request->get('timer');

    if ($vchess_game->isPlayersMove($user) && $timer) {
      if ($vchess_game->getPlayerColor($user) === 'w') {
        $vchess_game->setWhiteTimeLeft($timer['white']);
      }
      if ($vchess_game->getPlayerColor($user) === 'b') {
        $vchess_game->setBlackTimeLeft($timer['black']);
      }
      $vchess_game->save();
    }
    return new AjaxResponse([
      'white_time' => $vchess_game->getWhiteTimeLeft(),
      'black_time' => $vchess_game->getBlackTimeLeft(),
    ]);
  }

}
