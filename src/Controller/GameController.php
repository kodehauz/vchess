<?php

namespace Drupal\vchess\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\gamer\Entity\GamerStatistics;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\vchess\Entity\Game;
use Drupal\vchess\Game\GamePlay;
use Drupal\vchess\GameManagementTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GameController extends ControllerBase {

  use GameManagementTrait;

  /**
   * page callback vchess_main_page to display main vchess window
   */
  public function mainPage() {
    $user = User::load($this->currentUser()->id());
    if ($user->isAuthenticated()) {
      $player = GamerStatistics::loadForUser($user);
      $build['title'] = [
        '#type' => 'markup',
        '#markup' => new FormattableMarkup('<p>My current rating: <b>@rating</b></p>', ['@rating' => $player->getRating()]),
      ];
      $build['links'] = $this->buildNewGameLinks();
      $games = Game::loadUsersCurrentGames($user);
      $build['my_games'] = $this->buildCurrentGamesTable($games, $user);

      // Get the list of all challenges.
      $challenges = Game::loadChallenges();
      $build['all_challenges'] = static::buildChallengesTable($challenges);

      $current_games = Game::countUsersCurrentGames($user);
      if ($player->getCurrent() !== $current_games) {
        // Log error if there is a discrepancy.
        $this->getLogger('vchess')
          ->error($this->t('Stats for current games from gamer was @gamer_current but vChess calculates @vchess',
          [
            '@gamer_current' => $player->getCurrent(),
            '@vchess' => $current_games,
          ]));

        $player
          ->setCurrent($current_games)
          ->save();
      }
      $build['stats'] = $this->buildPlayerStatsTable($user);

      return $build;
    }
    else {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('Please log in to access this page.'),
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
              $markup_arguments['@alt'] = '1.green';
              // alt text is used so sort order is green, red, grey
            }
            else {
              $markup_arguments['mark'] = 'redmark.gif';
              $markup_arguments['@alt'] = '2.red';
            }
          }
          else {
            $markup_arguments['mark'] = 'greymark.gif';
            $markup_arguments['@alt'] = '3.grey';
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
      ['data' => $this->t('View')],
    ];

    // getting the current sort and order parameters from the url
    // e.g. q=vchess/my_current_games&sort=asc&order=White
    $sort = tablesort_get_sort($header);
    $order = tablesort_get_order($header);

    if (count($rows) > 1) {
      // sort the table data accordingly
      $rows = static::doNonSqlSort($rows, $sort, $order['sql']);
    }

    $entity_type = \Drupal::entityTypeManager()->getDefinition('vchess_game');
    return [
      '#type' => 'table',
      '#header' => $header,
      '#caption' => t('Current Games'),
      '#rows' => $rows,
      '#empty' => $empty,
      '#attributes' => [
        'class' => ['table', 'current-games-table', 'table-responsive', 'table-striped'],
      ],
      '#cache' => [
        'contexts' => $entity_type->getListCacheContexts(),
        'tags' => $entity_type->getListCacheTags(),
      ],
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
  public static function buildChallengesTable($games) {
    $rows = [];
    $user = User::load(\Drupal::currentUser()->id());
    $empty = '';
    if (count($games) > 0) {
      foreach ($games as $game) {
        $challenger = GamerStatistics::loadForUser($game->getChallenger());
        if ($challenger->getOwner()->id() !== $user->id() || MAY_PLAY_SELF) {
          $accept_link = t('<a href=":accept-link">Accept</a>',
            [':accept-link' => Url::fromRoute('vchess.accept_challenge', ['vchess_game' => $game->id()])->toString()]);
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
          'speed' => $game->getSpeed(),
          'accept' => $accept_link,
        ];
      }
    }
    else {
      $empty = t('There are currently no waiting challenges. You can <a href=":create-url">create a new challenge here.</a>',
        [':create-url' => Url::fromRoute('vchess.create_challenge')->toString()]);
    }

    $header = [
      ['data' => t('Challenger'), 'field' => 'challenger'],
      ['data' => t('Rating'), 'field' => 'rating'],
      ['data' => t('Time limit per move'), 'field' => 'speed'],
      t('Accept'),
    ];

    // getting the current sort and order parameters from the url
    // e.g. q=vchess/my_current_games&sort=asc&order=White
    $sort = tablesort_get_sort($header);
    $order = tablesort_get_order($header);

    if (count($rows) > 1) {
      // sort the table data accordingly
      $rows = static::doNonSqlSort($rows, $sort, $order['sql']);
    }

    $entity_type = \Drupal::entityTypeManager()->getDefinition('vchess_game');
    return [
      '#type' => 'table',
      '#header' => $header,
      '#caption' => t('Challenges'),
      '#rows' => $rows,
      '#empty' => $empty,
      '#attributes' => [
        'class' => ['table', 'challenges-table', 'table-responsive', 'table-striped'],
      ],
      '#cache' => [
        'contexts' => $entity_type->getListCacheContexts(),
        'tags' => $entity_type->getListCacheTags(),
      ],
    ];
  }

  /**
   * Get the stats for a particular player
   */
  protected function buildPlayerStatsTable(UserInterface $user) {
    $stats = GamerStatistics::loadForUser($user);

    $header = ['Played', 'Won', 'Drawn', 'Lost', 'Rating', 'Rating change', 'Current games', 'Streak'];
    $rows = [[
      $stats->getPlayed(), $stats->getWon(), $stats->getDrawn(),
      $stats->getLost(), $stats->getRating(), $stats->getRchanged(),
      $stats->getCurrent(), implode(' ', Game::getPlayerStreak($user, 5))
    ]];

    return [
      '#caption' => $this->t('Statistics for <b>%user</b>', ['%user' => $user->getDisplayName()]),
      '#type'   => 'table',
      '#header' => $header,
      '#rows'   => $rows,
      '#empty'  => 'Nothing to display.',
      '#attributes' => [
        'class' => ['table', 'stats-table', 'table-responsive', 'table-striped'],
      ],
      '#cache' => [
        'contexts' => Cache::mergeContexts($stats->getEntityType()->getListCacheContexts(), ['user']),
        'tags' => Cache::mergeTags($stats->getEntityType()->getListCacheTags(), ['user']),
      ],
    ];
  }

  /**
   * Menu callback to create new default challenge
   *
   * @todo this shares same code as CreateChallengeForm::submitForm() consider
   * refactoring.
   */
  public function createDefaultChallenge() {
    $user = User::load(\Drupal::currentUser()->id());

    // Check that user does not already have too many challenges pending.
    if (count(Game::loadChallenges($user)) <= VCHESS_PENDING_LIMIT) {
      $game = Game::create()
        ->setWhiteUser($user);
      $game->save();
      drupal_set_message($this->t('Challenge has been created.'));
      return new RedirectResponse(Url::fromRoute('vchess.game', ['vchess_game' => $game->id()])->toString());
    }
    else {
      drupal_set_message($this->t('You already have the maximum of @max challenges pending.', ['@max' => VCHESS_PENDING_LIMIT]));
      return new RedirectResponse(Url::fromRoute('vchess.challenges')->toString());
    }
  }

  public function acceptChallenge(Game $vchess_game) {
    if ($this->acceptGameChallenge($vchess_game)) {
      $vchess_game
        ->setStatus(GamePlay::STATUS_IN_PROGRESS)
        ->save();
      return new RedirectResponse(Url::fromRoute('vchess.game',
        ['vchess_game' => $vchess_game->id()])->toString());
    }
    return new RedirectResponse(Url::fromRoute('vchess.challenges')->toString());
  }

  /**
   * Accepts a challenge to play a particular game.
   *
   * @param \Drupal\vchess\Entity\Game $game
   *   The game pulled from the Request.
   *
   * @return bool
   *   Whether the challenge was successfully accepted or not.
   */
  protected function acceptGameChallenge(Game $game) {
    $user = User::load(\Drupal::currentUser()->id());

    // Check that the game has not already got players (should never happen!).
    if ($game->getWhiteUser() === NULL || $game->getBlackUser() === NULL) {
      $t_args = [
        '@username' => $user->getDisplayName(),
        '@game' => $game->label(),
      ];
      $color = $game->setPlayerRandomly($user);

      $extra = '';
      $its_your_move = '';
      if ($color === 'w') {
        $opponent = $game->getBlackUser();
        // @todo This is an outlier...???
        static::startGame($game, $user, $opponent);
        $its_your_move = t('Now, it is your move!');
        $t_args += [
          '@white' => $user->getDisplayName(),
          '@black' => $opponent->getDisplayName(),
        ];
      }
      else {
        $opponent = $game->getWhiteUser();
        // @todo This is an outlier...???
        static::startGame($game, $opponent, $user);
        $extra = t('Since you are playing black, you will have to wait for @opponent to move.<br />',
          ['@opponent' => $opponent->getDisplayName()]);
        $t_args += [
          '@white' => $opponent->getDisplayName(),
          '@black' => $user->getDisplayName(),
        ];
      }
      $t_args += [
        '@opponent' => $opponent->getDisplayName(),
        '@extra' => $extra,
        ':url' => Url::fromRoute('vchess.my_current_games')->toString(),
      ];
      $msg = t('Congratulations, you have started a game against <b>@opponent</b>.<br />@extra
      You can keep an eye on the status of this game and all your games on your <a href=":url">current games page</a>.<br />',
        $t_args);

      drupal_set_message($msg);

      if ($its_your_move) {
        drupal_set_message($its_your_move);
      }

      //      rules_invoke_event('vchess_challenge_accepted', $opponent, $gid);

      \Drupal::logger('vchess')
        ->info('Player @username has accepted challenge for game @game. W:@white vs. B:@black.', $t_args);

      return TRUE;

    }
    else {
      drupal_set_message(t('Players are already assigned so challenge cannot be fulfilled.'));

      \Drupal::logger('vchess')
        ->error('Players are already assigned so challenge cannot be fulfilled. Player @username accepted challenge for game @game. W:@white vs. B:@black.',
          ['@username' => $user->getDisplayName(),
            '@game' => $game->label(),
            '@white' => $game->getWhiteUser()->getDisplayName(),
            '@black' => $game->getBlackUser()->getDisplayName()
          ]);

      return FALSE;
    }
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

  /**
   * menu callback to display all active games
   */
  public function allCurrentGames() {
    $user = User::load($this->currentUser()->id());
    $out = [];
    if ($user->isAuthenticated()) {
      if (!$user->getAccountName()) {
        return $this->t('Please, register to play chess');
      }

      // Get the list of possible games to view
      $games = Game::loadAllCurrentGames();

      $out['links'] = $this->buildNewGameLinks();
      $out['current_games'] = $this->buildCurrentGamesTable($games, $user);
    }
    else {
      $out['#markup'] = $this->t('Please log in to access this page');
    }

    return $out;
  }


  /**
   * Get page of all current challenges
   */
  public function allChallenges() {
    // Get the list challenges available.
    $games = Game::loadChallenges();
    $build['links'] = $this->buildNewGameLinks();
    $build['challenges'] = static::buildChallengesTable($games);
    return $build;
  }

  /**
   * Controller callback to display current games for the logged in user.
   */
  public function myCurrentGames() {
    $user = User::load($this->currentUser()->id());
    $games = Game::loadUsersCurrentGames($user);
    $out['links'] = $this->buildNewGameLinks();
    $out['my_games'] = $this->buildCurrentGamesTable($games, $user);
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

    return $this->buildCurrentGamesTable($games, $user);
  }

  /**
   * Display the page for a given player
   *
   * @param UserInterface $player
   *   The player.
   */
  public function displayPlayer(UserInterface $player) {
    if ($player) {
      return [
        'stats' => $this->buildPlayerStatsTable($player),
        'games' => $this->usersCurrentGames($player),
      ];
    }
    throw new NotFoundHttpException();
  }

  /**
   * page callback to display the table of players
   */
  public function displayPlayers() {
    $this->checkForLostOnTimeGames();

    return $this->playersTable();
  }

  /**
   * Checks through all the current games to see if any have been lost on time.
   *
   * If games are found which are lost on time, then the game is finished and
   * the player statistics are updated
   */
  protected function checkForLostOnTimeGames() {
    $games = Game::loadAllCurrentGames();
    foreach ($games as $game) {
      if ($game->isLostOnTime()) {
        GamerStatistics::updatePlayerStatistics($game);
      }
    }
  }

  protected function playersTable() {
    /** @var \Drupal\user\UserInterface $user */
    $rows = [];
    foreach (User::loadMultiple() as $user) {
      $stats = GamerStatistics::loadForUser($user);
      $rows[] = [
        'uid' => $user->id(),
        'name' => '<a href="'
          . Url::fromRoute('vchess.player', ['player' => $user->id()])->toString()
          . '">' . $user->getDisplayName() . '</a>',
        'rating' => $stats->getRating(),
        'played' => $stats->getPlayed(),
        'won' => $stats->getWon(),
        'lost' => $stats->getLost(),
        'drawn' => $stats->getDrawn(),
        'rating_change' => $stats->getRchanged(),
        // the name tag is used so that the column still sorts correctly
        'current' => '<a name="' . $stats->getCurrent() . '" href="'
          . Url::fromRoute('vchess.user_current_games', ['user' => $user->id()])->toString()
          . '">' . $stats->getCurrent() . '</a>',
      ];
    }

    $header = [
      ['data' => $this->t('uid'), 'field' => 'uid'],
      ['data' => $this->t('name'), 'field' => 'name'],
      ['data' => $this->t('rating'), 'field' => 'rating'],
      ['data' => $this->t('played'), 'field' => 'played'],
      ['data' => $this->t('won'), 'field' => 'won'],
      ['data' => $this->t('lost'), 'field' => 'lost'],
      ['data' => $this->t('drawn'), 'field' => 'drawn'],
      ['data' => $this->t('rating change'), 'field' => 'rating_change'],
      ['data' => $this->t('in progress'), 'field' => 'current'],
    ];

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => 'The message to display in an extra row if table does not have any rows.',
      '#cache' => [
        'contexts' => Cache::mergeContexts($stats->getEntityType()->getListCacheContexts(), ['user']),
        'tags' => Cache::mergeTags($stats->getEntityType()->getListCacheTags(), ['user']),
      ],
    ];
  }

  /**
   * Get the link to the page for creating a new game
   */
  protected function buildNewGameLinks() {
    $links = [
      '#prefix' => '<div class="vchess-game-links">',
      '#suffix' => '</div>',
      '#cache' => [
        'max-age' => Cache::PERMANENT,
      ],
    ];
    $links['create_challenge'] = [
      '#prefix' => '<div class="create-challenge button">',
      '#suffix' => '</div>',
      '#type' => 'link',
      '#title' => $this->t('Create challenge'),
      '#url' => Url::fromRoute('vchess.create_challenge'),
    ];
    $links['create_random_game'] = [
      '#prefix' => '<div class="create-random-game button">',
      '#suffix' => '</div>',
      '#type' => 'link',
      '#title' => $this->t('New random game'),
      '#url' => Url::fromRoute('vchess.random_game_form'),
    ];
    $links['create_opponent_game'] = [
      '#prefix' => '<div class="create-opponent-game button">',
      '#suffix' => '</div>',
      '#type' => 'link',
      '#title' => $this->t('New opponent game'),
      '#url' => Url::fromRoute('vchess.opponent_game_form'),
    ];
    $links['#attached']['library'][] = 'vchess/vchess';

    return $links;
  }


  /**
   * Get simple won/lost/drawn stats for a player.
   */
  protected function buildPlayerStatistics(UserInterface $player) {
    $user = User::load(\Drupal::currentUser()->id());
    $stats = GamerStatistics::loadForUser($player);

    if ($player->id() === $user->id()) {
      $title = $this->t('Here are your basic statistics<br />');
    }
    else {
      $title = $this->t('Here are the basic statistics for <b>@user</b><br />',
        ['@user' => $player->getDisplayName()]);
    }

    return [
      'title' => $title,
      'description' => [
        '#prefix' => '<div style="text-align:center;">',
        '#markup' => $this->t('Won: @won; Lost: @lost; Drawn: @drawn</div>',
           ['@won' => $stats->getWon(), '@lost' => $stats->getLost(), '@drawn' => $stats->getDrawn()]),
        '#suffix' => "<br />",
      ],
    ];
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
    $vals = [
      GamePlay::TIME_UNITS_DAYS => $secs / 86400 % 7,
      GamePlay::TIME_UNITS_HOURS => $secs / 3600 % 24,
      GamePlay::TIME_UNITS_MINS => $secs / 60 % 60,
      GamePlay::TIME_UNITS_SECS => $secs % 60,
    ];

    $ret = [];

    $added = false;
    foreach ($vals as $k => $v) {
      if ($v > 0 || $added) {
        $added = true;
        $ret[] = $v . ' ' . $k;
      }
    }

    return implode(' ', $ret);
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
  protected static function doNonSqlSort($rows, $sort, $column) {
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
    if ($sort === 'asc') {
      asort($temp_array);
    }
    else {
      arsort($temp_array);
    }

    // Now we copy in this order into an array of new rows, e.g.
    //   $new_rows[0] = $rows[2];
    //   $new_rows[1] = $rows[0];
    //   $new_rows[2] = $rows[1];
    $new_rows = [];
    foreach ($temp_array as $index => $data) {
      $new_rows[] = $rows[$index];
    }

    return $new_rows;
  }

}
