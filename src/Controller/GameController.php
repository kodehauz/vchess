<?php

namespace Drupal\vchess\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\gamer\Entity\GamerStatistics;
use Drupal\gamer\GamerController;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\vchess\Entity\Game;
use Drupal\vchess\Form\GamePlayForm;
use Drupal\vchess\Game\GamePlay;
use Drupal\vchess\Entity\Scoresheet;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// @todo Temporary shim!!!
include __DIR__ . '/../../render.inc';

class GameController extends ControllerBase {

  /**
   * page callback vchess_main_page to display main vchess window
   */
  public function mainPage() {
    $user = User::load($this->currentUser()->id());
    if ($user->isAuthenticated()) {
      $gamefolder =  $this->config('vchess.settings')->get('game_files_folder');
      $res_games = $gamefolder;

//
//      if (!$user->getDisplayName()) {
//        $txt = t('Please, register to play chess');
//        return $txt;
//      }

      $player = GamerStatistics::loadForUser($user);
      $build['title'] = [
        '#type' => 'markup',
        '#markup' => new FormattableMarkup('<p>My current rating: <b>@rating</b></p>', ['@rating' => $player->getRating()]),
      ];
      $games = Game::loadUsersCurrentGames($user);
      $build['my_games'] = $this->buildCurrentGamesTable($games, $user);

      // Get the list of all challenges.
      $challenges = Game::loadChallenges();
      $build['all_challenges'] = $this->buildChallengesTable($challenges, $user);
      $build['links'] = $this->buildNewGameLinks();
      $build['stats'] = $this->buildPlayerStatsTable($user);

      return $build;
    }
    else {
      return [
        '#type' => 'markup',
        '#markup' => $this->t("Please log in to access this page"),
      ];
    }

  }

  /**
   * Constructs a current games table.
   *
   * @param \Drupal\vchess\Entity\Game[] $games
   *   The games for the user.
   * @param \Drupal\user\UserInterface $named_user
   *   The user.
   *
   * @return array
   *   A render array.
   */
  public function buildCurrentGamesTable(array $games, UserInterface $named_user) {
    global $base_url;
    $user = User::load($this->currentUser()->id());
    $rows = [];
    $empty = '';
    if (count($games) > 0) {
      foreach ($games as $game) {
        // We need to check first if the game has recently been lost on time, in
        // which case it is no longer a current game.  If it has, then this is the
        // first time it has been noticed (since this game was in "In progress")
        // and so we need to update the gamer statistics
        if ($game->isLostOnTime()) {
          GamerStatistics::updatePlayerStatistics($game);
        }
        else {
          $markup_arguments = [];
          if ($game->isUserPlaying($user)) {
            if ($game->isPlayersMove($user)) {
              $markup_arguments['mark'] = 'greenmark.gif';
              $markup_arguments['alt'] = '1.green'; // alt text is used so sort order is green, red, grey
            }
            else {
              $markup_arguments['mark'] = 'redmark.gif';
              $markup_arguments['alt'] = '2.red';
            }
          }
          else {
            $markup_arguments['mark'] = 'greymark.gif';
            $markup_arguments['alt'] = '3.grey';
          }

          if ($game->getTurn() === 'w') {
            $player_to_move = $game->getWhiteUser();
          }
          else {
            $player_to_move = $game->getBlackUser();
          }

          $time_left = $game->calculateTimeLeft();
          $markup_arguments += [
            ':src' => $base_url . "/" . drupal_get_path('module', 'vchess') . '/images/default/' . $markup_arguments['mark'],
            ':white-player-url' => Url::fromRoute('vchess.player', ['player' => $game->getWhiteUser()->getAccountName()])->toString(),
            '@white-player-name' => $game->getWhiteUser()->getDisplayName(),
            ':black-player-url' => Url::fromRoute('vchess.player', ['player' => $game->getBlackUser()->getAccountName()])->toString(),
            '@black-player-name' => $game->getBlackUser()->getDisplayName(),
            ':player-to-move-url' => Url::fromRoute('vchess.player', ['player' => $player_to_move->getAccountName()])->toString(),
            '@player-to-move-name' => $player_to_move->getDisplayName(),
            ':game-url' => Url::fromRoute('vchess.game', ['vchess_game' => $game->id()])->toString(),
            '@long-time' => sprintf("%07d", $time_left),
            '@time' => $this->formatUserFriendlyTime($time_left),
          ];
          $rows[] = [
            'move' => new FormattableMarkup('<img alt="@alt" src=":src">', $markup_arguments),
            'white' => new FormattableMarkup('<a href=":white-player-url">@white-player-name</a>', $markup_arguments),
            'black' => new FormattableMarkup('<a href=":black-player-url">@black-player-name</a>', $markup_arguments),
            'move_no' => $game->getMoveNumber(),
            // We use div id in secs to ensure sort works correctly
            'time_left' => new FormattableMarkup('<div id="@long-time">@time</div>', $markup_arguments),
            'speed' => $game->getSpeed(),
            'turn' => new FormattableMarkup('<a href=":player-to-move-url">@player-to-move-name</a>', $markup_arguments),
            'gid' => new TranslatableMarkup('<a href=":game-url">View</a>', $markup_arguments),
          ];
        }
      }
    }
    else {
      if ($user->id() == $named_user->id()) {
        $empty = $this->t("You currently do not have any games.<br /><br />You can find a <a href=':challenge-url'>list of open challenges here</a>, and you can <a href=':new-challenge-url'>create a new challenge here.</a>",
          [':challenge-url' => Url::fromRoute('vchess.challenges')->toString(), ':new-challenge-url' => Url::fromRoute('vchess.create_challenge')->toString()]);
      }
      else {
        $empty = t("@user does not have any current games.", ['@user' => $named_user->getDisplayName()]);
      }
    }

    $header = [
      ['data' => $this->t('Your move?'), 'field' => 'move'],
      ['data' => $this->t('White'), 'field' => 'white'],
      ['data' => $this->t('Black'), 'field' => 'black'],
      ['data' => $this->t('Move #'), 'field' => 'move_no'],
      ['data' => $this->t('Time left'), 'field' => 'time_left'],
      ['data' => $this->t('Time per move'), 'field' => 'speed'],
      ['data' => $this->t('Turn'), 'field' => 'turn'],
      $this->t('View'),
    ];

    // getting the current sort and order parameters from the url
    // e.g. q=vchess/my_current_games&sort=asc&order=White
    $sort = tablesort_get_sort($header);
    $order = tablesort_get_order($header);

    if (count($rows) > 1) {
      // sort the table data accordingly
      $rows = $this->doNonSqlSort($rows, $sort, $order['sql']);
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#caption' => t('Current Games'),
      '#rows' => $rows,
      '#empty' => $empty,
    ];
  }

  /**
   * Construct a challenges table.
   *
   * @param \Drupal\vchess\Entity\Game[] $games
   *   The games for the user.
   * @param \Drupal\user\UserInterface $named_user
   *   The user.
   *
   * @return array
   *   A render array.
   */
  public function buildChallengesTable($games, $named_user) {
    $rows = [];
    $user = User::load(\Drupal::currentUser()->id());
    $empty = "";
    if (count($games) > 0) {
      foreach ($games as $game) {
        $challenger = $game->getChallenger();
        if ($challenger->getOwner()->id() !== $user->id() || MAY_PLAY_SELF) {
          $accept_link = t('<a href=":accept-link">Accept</a>',
            [':accept-link' => Url::fromRoute('vchess.accept_challenge', ['game' => $game->id()])]);
        }
        else {
          $accept_link = t('Pending');
        }
        $rows[] = [
          'challenger' => new FormattableMarkup('<a href=":challenger-url">@challenger-name</a>', [
            ':challenger-url' => Url::fromRoute('vchess.player', ['player' => $challenger->getOwner()->id()])->toString(),
            '@challenger-name' => $challenger->getOwner()->getDisplayName()
          ]),
          'rating' => $challenger->getRating(),
          'speed' => $game->speed(),
          'gid' => $accept_link,
        ];
      }
    }
    else {
      $empty = $this->t('There are currently no waiting challenges. You can <a href=":create-url">create a new challenge here.</a>',
        [':create-url' => Url::fromRoute('vchess.create_challenge')->toString()]);
    }

    $header = [
      ['data' => t('Challenger'), 'field' => 'challenger'],
      ['data' => t('Rating'), 'field' => 'rating'],
      ['data' => t('Time limit per move'), 'field' => 'speed'],
      $this->t('Accept'),
    ];

    // getting the current sort and order parameters from the url
    // e.g. q=vchess/my_current_games&sort=asc&order=White
    $sort = tablesort_get_sort($header);
    $order = tablesort_get_order($header);

    if (count($rows) > 1) {
      // sort the table data accordingly
      $rows = $this->doNonSqlSort($rows, $sort, $order['sql']);
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#caption' => t('Challenges'),
      '#rows' => $rows,
      '#empty' => $empty,
    ];
  }

  /**
   * Get the stats for a particular player
   */
  protected function buildPlayerStatsTable(UserInterface $user) {
    $gamer_stats = GamerStatistics::loadForUser($user);
    $vchess_calculated = Game::countUsersCurrentGames($user);
    if ($gamer_stats->getCurrent() != $vchess_calculated) {
      $this->getLogger('vchess')->error($this->t('Stats for current games from gamer was @gamer_current but vchess calculates @vchess',
        [
          '@gamer_current' => $gamer_stats->getCurrent(),
          '@vchess' => $vchess_calculated,
        ]));

      $gamer_stats
        ->setCurrent($vchess_calculated)
        ->save();
    }

//        $out .= $this->buildPlayerStatistics($user);
    return GamerController::playerStatsTable($user);
  }

  /**
   * Menu callback to create new default challenge
   *
   * @todo this shares same code as CreateChallengeForm::submitForm() consider
   * refactoring.
   */
  public function createDefaultChallenge() {
    $user = User::load(\Drupal::currentUser()->id());

    // Check that user does not already have a challenge pending
    $pending = 0;
    $games = Game::loadChallenges();
    foreach ($games as $game) {
      if ($game->getChallenger()->id() === $user->id()) {
        $pending++;
      }
    }

    if ($pending <= VCHESS_PENDING_LIMIT) {
      $game = Game::create()
        ->setWhiteUser($user);
      $game->save();
      drupal_set_message($this->t('Challenge has been created.'));
      return new RedirectResponse(Url::fromRoute('vchess.game', ['game' => $game->id()])->toString());
    }
    else {
      drupal_set_message($this->t('You already have the maximum of @max challenges pending.', ['@max' => VCHESS_PENDING_LIMIT]));
      return new RedirectResponse(Url::fromRoute('vchess.challenges')->toString());
    }
  }

  /**
   * Accept a challenge to play a particular game.
   *
   * @param \Drupal\vchess\Entity\Game $game
   *   The game pulled from the Request.
   */
  public function acceptChallenge(Game $game) {
    $user = User::load($this->currentUser()->id());

    $this->getLogger(__METHOD__)->info("Player uid=%uid (%uname) has accepted challenge for game %gid. " .
      "white_uid=%white_uid, black_uid=%black_uid. ",
        ['%uid' => $user->id(),
         '%uname' => $user->getDisplayName(),
         '%gid' => $game->id(),
         '%white_uid' => $game->getWhiteUser()->id(),
         '%black_uid' => $game->getBlackUser()->id()]);

    // Check that the game has not already got players (should never happen!)
    if ($game->getWhiteUser() === NULL || $game->getBlackUser() === NULL) {
      if ($game->getWhiteUser() === NULL) {
        $game->setWhiteUser($user);
      }
      else {
        $game->setBlackUser($user);
      }

      GamerController::startGame($game->getWhiteUser(), $game->getBlackUser());

      $extra = "";
      $its_your_move = "";
      if ($game->player_color($user->uid) == 'w') {
        $opponent_name = $game->black_player()->name();
        $opponent_user = user_load($game->black_uid());
        $its_your_move = "Now, it's your move!";
      }
      else {
        $opponent_name = $game->white_player()->name();
        $opponent_user = user_load($game->white_uid());
        $extra = "Since you are playing black, you will have to wait for $opponent_name to move.<br />";
      }
      $msg = "Congratulations, you have started a game against <b>" . $opponent_name . "</b>.<br />";
      $msg .= $extra;
      $msg .= "You can keep an eye on the status of this game and all your games on your <a href='" . url("vchess/my_current_games") .
        "'>current games page</a>.<br />";
      $msg .= $its_your_move;

      rules_invoke_event('vchess_challenge_accepted', $opponent_user, $gid);

      drupal_set_message($msg);

      drupal_goto("vchess/game/" . $gid);
    }
    else {
      watchdog(__FUNCTION__, "Players are already assigned so challenge cannot be fulfilled. " .
        "Player uid=%uid (%uname) accepted challenge for game %gid. " .
        "white_uid=%white_uid, black_uid=%black_uid. ",
        array('%uid' => $user->uid,
          '%uname' => $user->name,
          '%gid' => $gid,
          '%white_uid' => $game->white_uid(),
          '%black_uid' => $game->black_uid()
        ),
        WATCHDOG_ERROR);
    }

    return $this->displayGame($game);
  }

  /**
   * Display the game (board, scoresheet) for the given game entity.
   *
   * @param \Drupal\vchess\Entity\Game $vchess_game
   *   The game to be displayed
   *
   * @return array
   *   The render array for the game display page.
   */
  public function displayGame(Game $vchess_game) {
    return \Drupal::formBuilder()->getForm(GamePlayForm::class, $vchess_game);
  }

  /**
   * menu callback to display all active games
   */
  public function allCurrentGames() {
    $user = User::load($this->currentUser());
    if ($user->isAuthenticated()) {
      $gamefolder = $this->config('vchess.settings')->get('game_files_folder');
      $res_games = $gamefolder;

      if (!$user->getAccountName()) {
        $txt = $this->t('Please, register to play chess');
        return $txt;
      }

      // Get the list of possible games to view
      $games = Game::loadAllCurrentGames();

      $out = $this->buildCurrentGamesTable($games, $user);
      $out .= $this->buildNewGameLinks();
    }
    else {
      $out = "Please log in to access this page";
    }

    return $out;
  }


  /**
   * Get page of all current challenges
   */
  public function allChallenges() {
    $user = User::load($this->currentUser()->id());
    // Get the list of possible games to view
    $games = Game::loadChallenges();
    $build['challenges'] = $this->buildChallengesTable($games, $user);
    $build['links'] = $this->buildNewGameLinks();

    return $build;
  }

  /**
   * Controller callback to display current games for the logged in user.
   */
  public function myCurrentGames() {
    $user = User::load($this->currentUser()->id());
    $games = Game::loadUsersCurrentGames($user);
    $out['my_games'] = $this->buildCurrentGamesTable($games, $user);
    $out['links'] = $this->buildNewGameLinks();
    return $out;
  }

  /**
   * Get table of current games for the current player
   *
   * @param $name
   *   Name of player for whom list of games is required
   *
   * @return
   *   Table of current games as HTML
   */
  public function usersCurrentGames(UserInterface $user) {
    // Get the list of possible games to view
    $games = Game::loadUsersCurrentGames($user);

    $html = $this->buildCurrentGamesTable($games, $user);

    return $html;
  }

  /**
   * Display the page for a given player
   *
   * @param $uid
   */
  public function displayPlayer($name) {
    $player = $this->entityTypeManager()->getStorage('user')->loadByProperties(['name' => $name]);

    if ($player = reset($player)) {
      $html = GamerController::playerStatsTable($player);
      $html .= $this->usersCurrentGames($player);

      return $html;
    }
    else {
      throw new NotFoundHttpException();
    }
  }

  /**
   * page callback to display the table of players
   */
  public function displayPlayers() {
    GamePlay::checkForLostOnTimeGames();

    return GamerStatistics::players();
  }

  /**
   * Get the link to the page for creating a new game
   */
  protected function buildNewGameLinks() {
    $links = [
      '#prefix' => '<p>',
      '#suffix' => '</p>'
    ];

    $links['create_challenge'] = [
      '#type' => 'link',
      '#title' => $this->t('Create challenge'),
      '#url' => Url::fromRoute('vchess.create_challenge'),
    ];
    $links['create_random_game'] = [
      '#type' => 'link',
      '#title' => $this->t('New random game'),
      '#url' => Url::fromRoute('vchess.random_game_form'),
    ];
    $links['create_opponent_game'] = [
      '#type' => 'link',
      '#title' => $this->t('New opponent game'),
      '#url' => Url::fromRoute('vchess.opponent_game_form'),
    ];
    return $links;
  }


  /**
   * Get simple won/lost/drawn stats for a player.
   */
  protected function buildPlayerStatistics(UserInterface $stats_user) {
    global $user;

    $html = "";

    $user = User::load(\Drupal::currentUser());
    $player = GamerStatistics::load($stats_user->id());
    //   $stats = gamer_load_user_stats($stats_user->uid);

    if ($stats_user->id() === $user->id()) {
      $html .= "Here are your basic statistics<br />";
    }
    else {
      $html .= "Here are the basic statistics for <b>" . $stats_user->name . "</b><br />";
    }

    $html .= '<div style="text-align:center;">';
    $html .= t('won:') . " " . $player->won() . " " . t('lost:') . " " . $player->lost() . " " .
      t('drawn:') . " " . $player->drawn() . '</div>';
    $html .= "<br />";

    return $html;
  }

  /**
   * A single test
   *
   * This function is for running individual tests normally found
   * within the vchess.test file, but without all the heavy setup
   * and teardown time which those functions need.
   */
  public function testVchess() {
    $html = "";

    GamerController::startGame(1, 1);

    $player = new Player(1);
    $player->set_current(-25);

    $html .= "time() is: " . date("Y-m-d H:i:s", time()) . "<br />";
    $html .= "SERVER REQUEST_TIME is: " . date("Y-m-d H:i:s", $_SERVER['REQUEST_TIME']) . "<br />";
    $html .= "gmdate() is: " . gmdate("Y-m-d H:i:s");

    return $html;
  }

  /**
   * Create a user-friendly view of a time, e.g. "56 mins 34 secs"
   *
   * @param $secs
   *   Number of seconds
   *
   * @return
   *   A user-friendly string of the time, e.g. "2 days 23 hours 56 mins 34 secs"
   */
  protected function formatUserFriendlyTime($secs) {
    $vals = array(
      GamePlay::TIME_UNITS_DAYS => $secs / 86400 % 7,
      GamePlay::TIME_UNITS_HOURS => $secs / 3600 % 24,
      GamePlay::TIME_UNITS_MINS => $secs / 60 % 60,
      GamePlay::TIME_UNITS_SECS => $secs % 60);

    $ret = array();

    $added = false;
    foreach ($vals as $k => $v) {
      if ($v > 0 || $added) {
        $added = true;
        $ret[] = $v . " " . $k;
      }
    }

    return join(' ', $ret);
  }

  /**
   * Sort the table rows
   *
   * The sort is performed on the column indicated by the
   * $column variable in the sort direction indicated by
   * the $sort variable.
   *
   * @param $rows
   *   The rows to be sorted
   * @param $sort
   *   The sort direction, either 'asc' or 'desc'
   * @param $column
   *   The title of column to be ordered
   *
   * @return
   *   An array of rows sorted according to the column mentioned
   */
  protected function doNonSqlSort($rows, $sort, $column) {
    // Example:
    //   $rows = array(
    //     0 => array("fruit" => "apple", "color" => "red"),
    //     1 => array("fruit" => "banana", "color" => "yellow")
    //     2 => array("fruit" => "kiwi", "color" => "green")
    //   );
    // We cannot sort the array as is, since the array is an array of rows, so
    // we first create a simple array containing just the column we want to sort
    // e.g. if $column is 'color' then:
    //   $temp_array = array(
    //    0 => "red",
    //    1 => "yellow",
    //    2 => "green"
    //  );
    foreach ($rows as $row) {
      $temp_array[] = $row[$column];
    }

    // Now we sort this temp array, but we preserve the keys, e.g.
    //   $temp_array = array(
    //    2 => "green"
    //    0 => "red",
    //    1 => "yellow",
    //   );
    // The actual sort order depends on whether this is 'asc' or 'desc'
    if ($sort == 'asc') {
      asort($temp_array);
    }
    else {
      arsort($temp_array);
    }

    // Now we copy in this order into an array of new rows, e.g.
    //   $new_rows[0] = $rows[2];
    //   $new_rows[1] = $rows[0];
    //   $new_rows[2] = $rows[1];
    foreach ($temp_array as $index => $data) {
      $new_rows[] = $rows[$index];
    }

    return $new_rows;
  }
}
