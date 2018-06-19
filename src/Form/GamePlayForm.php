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

    switch ($game->getStatus()) {
      case GamePlay::STATUS_IN_PROGRESS:
        // No message needs to be set.
        break;

      case GamePlay::STATUS_AWAITING_PLAYERS:
        drupal_set_message('This game is awaiting 1 or more players.');
        break;

      case GamePlay::STATUS_WHITE_WIN:
        drupal_set_message('This game was won by white.');
        break;

      case GamePlay::STATUS_BLACK_WIN:
        drupal_set_message('This game was won by black.');
        break;

      case GamePlay::STATUS_DRAW:
        drupal_set_message('This game was drawn.');
        break;

      case GamePlay::STATUS_DRAW_OFFERED_WHITE:
        drupal_set_message('Draw offered by white.');
        break;

      case GamePlay::STATUS_DRAW_OFFERED_BLACK:
        drupal_set_message('Draw offered by black.');
        break;

    }

    $form['board'] = [
      '#prefix' => '<div id="board">',
      '#suffix' => '</div>',
      'timer' => [
        '#type' => 'vchess_game_timer',
        '#game' => $game,
        '#player' => $player_color,
        '#active' => $player_may_move,
      ],
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
      '#attributes' => [
        'style' => ['visibility:hidden;'],
      ],
      '#name' => 'move_button',
    ];

    $form['actions'] = ['#type' => 'actions'];

    $form['refresh_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Refresh'),
      '#name' => 'refresh_button',
      '#ajax' => [
        'callback' => '::refreshBoard',
        'wrapper' => 'vchess-container',
      ],
    ];

    $form['actions']['flip_board_button'] = [
      '#type' => 'submit', // For now!
      '#value' => $this->t('Flip board'),
      '#name' => 'flip_button',
    ];

    if ($game->isMoveMade()
      && !$game->isGameOver()
      && $game->isPlayersMove($user)) {
      $form['actions']['resign_button'] = [
        '#type' => 'submit',
        '#value' => $this->t('Resign'),
        '#name' => 'resign_button',
      ];
      if (!$game->isDrawOffered()) {
        $form['actions']['offer_draw_button'] = [
          '#type' => 'submit',
          '#value' => $this->t('Offer Draw'),
          '#name' => 'offer_draw_button',
        ];
      }
      else {
        if ($game->isDrawOfferedTo($user)) {
          $form['draw_offered'] = [
            '#type' => 'hidden',
            '#value' => $game->getStatus(),
          ];
        }
      }
    }

    $form['move'] = [
      '#type' => 'hidden',
      '#default_value' => '',
    ];

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
    else if ($form_state->getTriggeringElement()['#name'] === 'offer_draw_button') {
      $this->offerDraw($form, $form_state);
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
    $uid = $this->currentUser()->id();

    $vchess_board = \Drupal::state()->get('vchess_board', []);
    if (!isset($vchess_board['flipped'][$gid][$uid])) {
      $vchess_board['flipped'][$gid][$uid] = FALSE;
    }

    if ($vchess_board['flipped'][$gid][$uid]) {
      drupal_set_message($this->t('Flip now OFF!'));
      $vchess_board['flipped'][$gid][$uid] = FALSE;
    }
    else {
      drupal_set_message($this->t('Flip now ON!'));
      $vchess_board['flipped'][$gid][$uid] = TRUE;
    }

    \Drupal::state()->set('vchess_board', $vchess_board);
  }

  /**
   * Resign from a particular game.
   *
   * This is the form handler for the resignation button.
   */
  protected function resignGame(array &$form, FormStateInterface $form_state) {
    $user = User::load($this->currentUser()->id());
    if ($this->game->isUserPlaying($user)) {
      $gameplay = new GamePlay($this->game);
      $gameplay->resign($user);
      // Update the player's times left.
      $this->game
        ->setWhiteTimeLeft($form_state->getValue(['timer', 'white_time']))
        ->setBlackTimeLeft($form_state->getValue(['timer', 'black_time']))
        ->save();
      GamerStatistics::updatePlayerStatistics($this->game);
      drupal_set_message($this->t('You have now resigned.'));
    }
    else {
      drupal_set_message($this->t('Not your turn to play'));
    }
  }

  /**
   * Sets up a draw offer.
   *
   * This is the form handler for the offer draw button.
   */
  protected function offerDraw(array &$form, FormStateInterface $form_state) {
    $user = User::load($this->currentUser()->id());
    if ($this->game->isUserPlaying($user)) {
      $gameplay = new GamePlay($this->game);
      $gameplay->offerDraw($user);
      $this->game
        ->setWhiteTimeLeft($form_state->getValue(['timer', 'white_time']))
        ->setBlackTimeLeft($form_state->getValue(['timer', 'black_time']))
        ->save();
      drupal_set_message($this->t('You have offered a draw.'));
    }
    else {
      drupal_set_message($this->t('Not your turn to play'));
    }
  }

  /**
   * Makes the move specified.
   *
   * This is the form handler for the make move button.
   */
  protected function makeMove(array &$form, FormStateInterface $form_state) {
    $user = User::load($this->currentUser()->id());
    if (!$this->game->isUserPlaying($user)) {
      drupal_set_message($this->t('Not your turn to play'));
      return;
    }

    // Command: e.g. Pe2-e4
    if ($cmd = $form_state->getValue('cmd')) {
      $game = $this->game;

      $gameplay = new GamePlay($game);
      $messages = [];
      $errors = [];

      if ($cmd === 'abort') {
        $move_made = $gameplay->abort($user, $messages, $errors);
      }
      elseif ($cmd === 'accept-draw') {
        $move_made = $gameplay->acceptDraw($user, $messages, $errors);
      }
      elseif ($cmd === 'refuse-draw') {
        $move_made = $gameplay->refuseDraw($user, $messages, $errors);
      }
      else { // try as chess move
        /** @var \Drupal\vchess\Entity\Move $move */
        $move = Move::create()->setLongMove($cmd);
        $move_made = $gameplay->makeMove($user, $move, $messages, $errors);
      }

      // Update the player's times left.
      $this->game
        ->setWhiteTimeLeft($form_state->getValue(['timer', 'white_time']))
        ->setBlackTimeLeft($form_state->getValue(['timer', 'black_time']));

      // Only save move and game if a move has actually been made.
      if ($move_made) {
        // Save game.
        $game->save();

        // Ensure that the other player is informed
//      rules_invoke_event('vchess_move_made', $game->white_player(), $game->black_player());

//        rules_invoke_event('vchess_move_made', $opponent,
//          $gid, $game->last_move()->algebraic());

        if ($game->isGameOver()) {
          GamerStatistics::updatePlayerStatistics($game);
        }

        $this->sendEmailNotification();
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

  protected function sendEmailNotification() {
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

}


