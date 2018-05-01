<?php

namespace Drupal\Tests\vchess\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\vchess\Entity\Game;
use Drupal\vchess\Game\Board;
use Drupal\vchess\Game\GamePlay;

/**
 * @group vchess
 * @coversDefaultClass \Drupal\vchess\Game\GamePlay
 */
class GamePlayTest extends KernelTestBase {

  public static $modules = ['user', 'gamer', 'pos', 'vchess'];

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

  public function testFullGameSequence() {
      $white = User::create(['name' => 'white']);
      $black = User::load($this->currentUser()->id());
      $game = Game::create();
      static::initializeGame($game, $white, $black, $white, 3600, 5);

      $movelist = explode("\n", self::TEST_STRING);
      $gameplay = new GamePlay($game);
      $messages = [];
      $errors = [];
      foreach ($movelist as $move) {
        list($move_number, $white_move, $black_move) = explode(" ", $move);
        $white_move_object = Move::create()->setLongMove($white_move);
        $move_made = $gameplay->makeMove($user, $white_move_object, $messages, $errors);
        $black_move_object = Move::create()->setLongMove($black_move);
        $move_made = $gameplay->makeMove($user, $black_move_object, $messages, $errors);
      }
      /** @var \Drupal\vchess\Entity\Move $move */
      //$game->save();
      $this->assertEquals("")
  }

  const TEST_STRING  =<<<EOF
1. Pe2-e4e Pe7-e5
2. Ng1-f3 Nb8-c6
3. Bf1-b5 Ng8-f6
4. Pd2-d3 Bf8-c5
5. Bb5xc6 Pd7xc6b
6. Ke1-g1 Nf6-d7
7. Nb1-d2 Ke8-g8
8. Qd1-e1 Pf7-f6
9. Nd2-c4 Rf8-f7
10. Pa2-a4 Bc5-f8
11. Kg1-h1 Nd7-c5
12. Pa4-a5 Nc5-e6
13. Nc4xe5 Pf6xe5
14. Nf3xe5 Rf7-f6
15. Ne5-g4 Rf6-f7
16. Ng4-e5 Rf7-e7
17. Pa5-a6 Pc6-c5
18. Pf2-f4 Qd8-e8
19. Pa6xb7 Bc8xb7
20. Qe1-a5 Ne6-d4
21. Qa5-c3 Re7-e6
22. Bc1-e3 Re6-b6
23. Ne5-c4 Rb6-b4
24. Pb2-b3 Pa7-a5
25. Ra1xa5 Ra8xa5
26. Nc4xa5 Bb7-a6
27. Be3xd4 Rb4xd4
28. Na5-c4 Rd4-d8
29. Pg2-g3 Ph7-h6
30. Qc3-a5 Ba6-c8
31. Qa5xc7 Bc8-h3
32. Rf1-g1 Rd8-d7
33. Qc7-e5 Qe8xe5
34. Nc4xe5 Rd7-a7
35. Ne5-c4 Pg7-g5
36. Rg1-c1 Bf8-g7
37. Nc4-e5 Ra7-a8
38. Ne5-f3 Bg7-b2
39. Rc1-b1 Bb2-c3
40. Nf3-g1 Bh3-d7
41. Ng1-e2 Bc3-d2
42. Rb1-d1 Bd2-e3
43. Kh1-g2 Bd7-g4
44. Rd1-e1 Be3-d2
45. Re1-f1 Ra8-a2
46. Ph2-h3 Bg4xe2
47. Rf1-f2 Bd2xf4
48. Rf2xe2 Bf4-e5
49. Re2-f2 Kg8-g7
50. Pg3-g4 Be5-d4
51. Rf2-e2 Kg7-f6
52. Ne4-e5 Bd4xe5
53. Kg2-f3 Ra2-a1
54. Re2-f2 Ra1-e1
55. Kf3-g2 Be5-f4
56. Pc2-c3 Re1-c1
57. Pd3-d4 Rc1xc3
58. Pd4xc5 Rc3xc5
59. Pb3-b4 Rc5-c3
60. Ph3-h4 Kf6-e5
61. Ph4xg5 Ph6xg5
62. Rf2-e2 Ke5-f6
63. Kg2-f2 Bf4-e5
64. Re2-a2 Rc3-c4
65. Ra2-a6 Kf6-e7
EOF;

}




class TestGamePlay extends GamePlay {

  public function getGame() {
    return $this->game;
  }

}
