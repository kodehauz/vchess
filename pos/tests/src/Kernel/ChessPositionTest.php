<?php

namespace Drupal\Tests\vchess\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\pos\Entity\ChessPosition;

/**
 * @group vchess_position
 * @coversDefaultClass \Drupal\pos\Entity\ChessPosition
 */
class ChessPositionTest extends KernelTestBase {

  public static $modules = ['system', 'user', 'vchess', 'pos', 'gamer'];

  public function setUp() {
    parent::setUp();

    $this->installSchema('system', 'sequences');
    $this->installEntitySchema('user');
    $this->installEntitySchema('vchess_game');
    $this->installEntitySchema('vchess_position');
  }

  public function _testGetterSetters() {
    $board = $this->randomString();
    $castling = $this->randomString();
    $en_passant = $this->randomString();
    $title = $this->randomString();
    $description = $this->randomString();

    /** @var \Drupal\pos\Entity\ChessPosition $game */
    $game = ChessPosition::create()
      ->setBoard($board)
      ->setCastling($castling)
      ->setEnPassant($en_passant)
      ->setTitle($title)
      ->setDescription($description);
    $game->save();

    /** @var \Drupal\pos\Entity\ChessPosition $saved_game */
    $saved_game = ChessPosition::load($game->id());
    $this->assertEquals($board, $saved_game->getBoard());
    $this->assertEquals($castling, $saved_game->getCastling());
    $this->assertEquals($en_passant, $saved_game->getEnPassant());
    $this->assertEquals($title, $saved_game->getTitle());
    $this->assertEquals($description, $saved_game->getDescription());
  }

  public function testGetPositionLabels(){
    $position1 = ChessPosition::create()
      ->setBoard('4k3/8/8/2p5/8/8/2P5/4K5')
      ->setTitle('Pawn promotion position');
    $position1->save();
    $position2 = ChessPosition::create()
      ->setBoard('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR')
      ->setTitle('Default Value');
    $position2->save();
    $position3 = ChessPosition::create()
      ->setBoard('k7/4P3/8/8/8/8/8/K7')
      ->setTitle('New board');
    $position3->save();

//    $game_position = ChessPosition::getPositionLabels();
//    $this->assertEquals($position1, $game_position->getBoard());
//    $this->assertEquals($position1, $game_position->getTitle());
//    $this->assertEquals($position2, $game_position->getBoard());
//    $this->assertEquals($position2, $game_position->getTitle());
//    $this->assertEquals($position3, $game_position->getBoard());
//    $this->assertEquals($position3, $game_position->getTitle());
  }
}
