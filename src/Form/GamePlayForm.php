<?php

namespace Drupal\vchess\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gamer\Entity\GamerStatistics;
use Drupal\user\Entity\User;
use Drupal\vchess\Entity\Game;
use Drupal\vchess\Entity\Move;
use Drupal\vchess\Game\GamePlay;

class GamePlayForm extends FormBase {

  /**
   * @var \Drupal\vchess\Entity\Game
   */
  protected $game;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'vchess_game_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\vchess\Entity\Game $game */
    $game = $this->game = Game::load($this->getRequest()->get('vchess_game'));

    // Current user viewing the game.
    $user = User::load($this->currentUser()->id());

    // Find the player color
    $player_color = $game->getPlayerColor($user);

    // Find out if the player has the move or not
    if ($game->isPlayersMove($user) && $game->getStatus() === GamePlay::STATUS_IN_PROGRESS) {
      $player_may_move = TRUE;
    }
    else {
      $player_may_move = FALSE;
    }

    $form['#prefix'] = '<div id="vchess-container">';
    $form['#suffix'] = '</div>';

    // Display game heading, e.g. "white: admin - black: hugh"
    $form['header'] = [
      '#markup' => '<div style="text-align:center;">white: <b>'
        . $game->getWhiteUser()->getDisplayName()
        . '</b> -   black: <b>'
        . $game->getBlackUser()->getDisplayName() . '</b>'
        . '</div>',
    ];

    $form['board'] = [
      '#prefix' => '<div id="board">',
      '#suffix' => '</div>',
      'game' => [
        '#cache' => [
          'max-age' => 0,
        ],
        '#type' => 'vchess_game',
        '#game' => $game,
        '#player' => $player_color,
        '#active' => $player_may_move,
        '#flipped' => $this->isBoardFlipped(),
        '#refresh_interval' => 5,
      ],
    ];

    /*
     * Render command form which contains information about players and last
     * moves and all main buttons (shown when necessary). The final command
     * is mapped to hidden field 'cmd' on submission. Show previous command
     * result $cmdres if set or last move if any. Fill move edit with $move
     * if set (to restore move when notes were saved).
     */
    
//    $form['commands'] = [
//      '#prefix' => '<div id="board-commands">',
//      'command_form' => \Drupal::formBuilder()->getForm(GamePlayForm::class, $game),
//      '#suffix' => '</div>',
//    ];

//    $move = "";

    $form['cmd'] = [
      '#type' => 'hidden',
      '#default_value' => '',
    ];

    $form['comment'] = [
      '#type' => 'value',
      '#default_value' => '',
    ];

    $form['privnotes'] = [
      '#type' => 'value',
      '#default_value' => '',
    ];

    $form['move_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Make move'),
//    '#attributes' => ['class' => 'invisible'],
      '#attributes' => ['style' => ['visibility:hidden;']],
      '#name' => 'move_button',
    ];

    $form['flip_board_button'] = [
      '#type' => 'submit', // For now!
      '#value' => $this->t('Flip board'),
      '#name' => 'flip_button',
    ];

    if ($game->isMoveMade()
      && !$game->isGameOver()
      && $game->isUserPlaying($user)) {
      $form['resign_button'] = [
        '#type' => 'submit',
        '#value' => $this->t('Resign'),
        '#name' => 'resign_button',
      ];
    }

    if ($game->isMoveMade()
      && !$game->isGameOver()
      && $game->isUserPlaying($user)) {
      $form['refresh_button'] = [
        '#type' => 'submit',
        '#value' => $this->t('Refresh'),
        '#name' => 'refresh_button',
        '#ajax' => [
          'callback' => '::refreshBoard',
          'wrapper' => 'vchess-container',
        ],
      ];
    }

    $form['move'] = [
      '#type' => 'hidden',
      '#default_value' => '',
    ];

    // Abort options.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#name'] === 'flip_button') {
      $this->flipBoard($form, $form_state);
    }
    else if ($form_state->getTriggeringElement()['#name'] === 'resign_button') {
      $this->resignGame($form, $form_state);
    }
    else if ($form_state->getTriggeringElement()['#name'] === 'move_button') {
      $this->makeMove($form, $form_state);
    }
  }

  /**
   * Updates the state variable for board flipping.
   */
  protected function flipBoard(array &$form, FormStateInterface $form_state) {
    $gid = $this->game->id();

    $vchess_board = \Drupal::state()->get('vchess_board', []);
    if (!isset($vchess_board['flipped'][$gid])) {
      $vchess_board['flipped'][$gid] = FALSE;
    }

    if ($vchess_board['flipped'][$gid]) {
      drupal_set_message($this->t('Flip now OFF!'));
      $vchess_board['flipped'][$gid] = FALSE;
    }
    else {
      drupal_set_message($this->t('Flip now ON!'));
      $vchess_board['flipped'][$gid] = TRUE;
    }

    \Drupal::state()->set('vchess_board', $vchess_board);
  }

  /**
   * Resign from a particular game.  This is the form handler for the resignation button.
   */
  protected function resignGame(array &$form, FormStateInterface $form_state) {
    $user = $this->currentUser();

    $gameplay = new GamePlay($this->game);
    $gameplay->resign($user);
    $this->game->save();

    if ($this->game->getStatus() == GamePlay::STATUS_BLACK_WIN) {
      $score = GamerStatistics::GAMER_BLACK_WIN; // white resigned
    }
    else {
      $score = GamerStatistics::GAMER_WHITE_WIN; // black resigned
    }

    GamerStatistics::updateUserStatistics($this->game->getWhiteUser(), $this->game->getBlackUser(), $score);

    drupal_set_message($this->t('You have now resigned.'));
  }

  protected function makeMove(array &$form, FormStateInterface $form_state) {
    // Command: e.g. Pe2-e4
    if ($cmd = $form_state->getValue('cmd')) {
      $user = User::load($this->currentUser()->id());
      $game = $this->game;

      /** @var \Drupal\vchess\Entity\Move $move */
      $move = Move::create()->setLongMove($cmd);
      $gameplay = new GamePlay($game);
      $messages = [];
      $errors = [];

      if ($cmd === 'abort') {
        $move_made = $gameplay->abort($user, $messages, $errors);
      }
      elseif ($cmd === 'acceptdraw') {
        $move_made = $gameplay->acceptDraw($user, $messages, $errors);
      }
      elseif ($cmd === 'refusedraw') {
        $move_made = $gameplay->rejectDraw($user, $messages, $errors);
      }
      else { // try as chess move
        $move_made = $gameplay->makeMove($user, $move, $messages, $errors);
      }

      // Only save move and game if a move has actually been made
//      if ($player_with_turn !== $game->getTurn()) {
      if ($move_made) {
        // Save game.
        $game->save();

        // Ensure that the other player is informed
//      rules_invoke_event('vchess_move_made', $game->white_player(), $game->black_player());
        $opponent = $game->getOpponent($user);

//        rules_invoke_event('vchess_move_made', $opponent,
//          $gid, $game->last_move()->algebraic());

        if ($game->getStatus() !== GamePlay::STATUS_IN_PROGRESS) {
          switch ($game->getStatus()) {
            case GamePlay::STATUS_WHITE_WIN:
              $score = GamerStatistics::GAMER_WHITE_WIN;
              break;
            case GamePlay::STATUS_BLACK_WIN:
              $score = GamerStatistics::GAMER_BLACK_WIN;
              break;
            case GamePlay::STATUS_DRAW:
              $score = GamerStatistics::GAMER_DRAW;
              break;
            default:
              $score = 0;
          }

          GamerStatistics::updateUserStatistics($game->getWhiteUser(), $game->getBlackUser(), $score);
        }

        // Send a notification if email address was supplied.
        //    if ($opponent == 'b') {
        //       $oid = $game['black'];
        //     }
        //     else {
        //       $oid = $game['white'];
        //     }
        //  $email=ioLoadUserEmailAddress($oid);
        $email = FALSE; // Hugh - force following condition to be FALSE
        if ($email) {
          $prot = ($GLOBALS['_SERVER']['HTTPS'] == 'on') ? 'https' : 'http';
          $url = $prot . '://' . $GLOBALS['_SERVER']['HTTP_HOST'] . $GLOBALS['_SERVER']['SCRIPT_NAME'] . '?gid=' . $gid;
          $message = "Dear $oid\n\n$uid has just moved.\n\nMove:\n$move\n\nIt is your turn now!\n\nEnter the game:\n$url";
          mail($email, "[OCC] " . $game['white'] . "vs" . $game['black'] . ": $move->long_format()",
            $message, 'From: ' . $mail_from);
        }
      }

      drupal_set_message(implode("\n", $messages));
    }
  }

  public function refreshBoard(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  protected function isBoardFlipped() {
    $gid = $this->game->id();
    $vchess_board = \Drupal::state()->get('vchess_board', []);
    return isset($vchess_board['flipped'][$gid]) && $vchess_board['flipped'][$gid] === TRUE;
  }

}


