<?php

namespace Drupal\Tests\vchess\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\vchess\Entity\Game;
use Drupal\vchess\Entity\Move;
use Drupal\vchess\Game\Board;
use Drupal\vchess\Game\GamePlay;
use Drupal\vchess\GameManagementTrait;

/**
 * @group vchess
 * @coversDefaultClass \Drupal\vchess\Game\GamePlay
 */
class GamePlayTest extends KernelTestBase {

  use GameManagementTrait;

  public static $modules = ['system', 'user', 'gamer', 'pos', 'vchess'];

  /**
   * GamePlay object under test.
   *
   * @var \Drupal\vchess\Game\GamePlay
   */
//  protected $gameplay;

  public function setUp() {
    parent::setUp();
//    $this->gameplay = new GamePlay();

    $this->installEntitySchema('vchess_game');
    $this->installEntitySchema('vchess_move');
    $this->installEntitySchema('gamer_statistics');
    $this->installEntitySchema('user');
    $this->installSchema('system','sequences');
  }

  public function testConstructor() {
    $gameplay = new GamePlay();
    // Confirm that a board is created.
    $this->assertNotNull($gameplay->getBoard());
    // Assert that board is default.
    $this->assertEquals(Board::BOARD_DEFAULT, $gameplay->getBoard()->getFenString());

    $game = Game::create()->setBoard(Board::BOARD_PROMOTION);
    $gameplay2 = new GamePlay($game);
    // Confirm that a board is created.
    $this->assertNotNull($gameplay->getBoard());
    // Assert that board is default.
    $this->assertEquals($game->getBoard(), $gameplay2->getBoard()->getFenString());

  }

  public function testSimpleMoveSequence() {
    $gameplay = new TestGamePlay(Game::create());
    // Confirm move number initially.
    $this->assertEquals(1, $gameplay->getGame()->getMoveNumber());

  }

  /**
   * @dataProvider providerFullGameSequence()
   */
  public function testFullGameSequence($game_pgn_file, $final_fen_position) {
      $white = User::create(['name' => 'white']);
      $white->save();

      $black = User::create(['name' => 'black']);
      $black->save();
      $game = Game::create()
        ->setBoard(Board::BOARD_DEFAULT);
      static::initializeGame($game, $white, $black, $white, 3600, 5);
      $gameplay = new GamePlay($game);

      $movelist = $this->loadPgnFile($game_pgn_file);
      $messages = [];
      $errors = [];
      foreach ($movelist as $move_no => $move) {
        list($white_move, $black_move) = explode(' ', trim($move));
        $white_move_object = Move::fromAlgebraic($white_move, $gameplay->getBoard(), 'w');
        if ($white_move_object) {
          $gameplay->makeMove($white, $white_move_object, $messages, $errors);
          $black_move_object = Move::fromAlgebraic($black_move, $gameplay->getBoard(), 'b');
          if ($black_move_object) {
            $gameplay->makeMove($black, $black_move_object, $messages, $errors);
          }
        }
      }
      /** @var \Drupal\vchess\Entity\Move $move */
      $this->assertEquals($final_fen_position, $game->getBoard());
  }

  public function providerFullGameSequence() {
    return [
      ['leela-chess-0-vs-phone.pgn', '8/1p6/3K4/6k1/2bN4/P7/8/8'],
      ['nebula-vs-leela-chess-0.pgn', '8/5p2/4p1Rk/4P3/7p/8/7K/4qq2'],
    ];
  }

  /**
   * Loads a PGN file and returns an array of moves.
   *
   * @param string $filename
   *   The file name.
   *
   * @return string[]
   *   An array of moves.
   */
  protected function loadPgnFile($filename) {
    // Read PGN file.
    $pgn = file_get_contents(__DIR__ . '/../../fixtures/games/' . $filename);

    // Remove PGN headers.
    $pgn = preg_replace('/\[[^\]]+\]/', '', $pgn);

    // Remove analyses.
    $pgn = preg_replace('/\{[^\}]+\}/', '', $pgn);
    $pgn = preg_replace('/\([^\)]+\)/', '', $pgn);

    // Remove excess whitespace around the text.
    $pgn = preg_split('/\d+\./', trim($pgn));
    unset($pgn[0]);

    return $pgn;
  }


  /**
   * Tests that pawn captures work as expected.
   */
  public function testPawnCapture() {
//    $fen = "rnbqkb1r/pp3ppp/4pn2/3p4/3P4/2N2N2/PPP2PPP/R1BQKB1R w KQkq - 2 6";
    // Full FENs cause the parser to hang.
    $fen = "rnbqkb1r/pp3ppp/4pn2/3p4/3P4/2N2N2/PPP2PPP/R1BQKB1R";
    $user1 = User::create(['name' => 'user1']);
    $user2 = User::create(['name' => 'user2']);
    $user1->save();
    $user2->save();
    $game = Game::create()
      ->setBoard($fen)
      ->setWhiteUser($user1)
      ->setBlackUser($user2);
    $game->save();
    $gamePlay = new GamePlay($game);

    $move = Move::create()->setLongMove('Pd4xPd5');
    $messages = $errors = [];
    $move_made = $gamePlay->makeMove($user1, $move, $messages, $errors);
    $this->assertFalse($move_made);
  }

}


class TestGamePlay extends GamePlay {

  public function getGame() {
    return $this->game;
  }

}
