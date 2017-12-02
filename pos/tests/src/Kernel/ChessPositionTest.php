<?php

namespace Drupal\Tests\pos\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\pos\Entity\ChessPosition;

/**
 * @group vchess
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

  public function testGetterSetters() {
    $board = $this->randomString();
    $castling = $this->randomString(5);
    $en_passant_square = $this->randomString(1);
    $label = $this->randomString();
    $description = $this->randomString();

    /** @var \Drupal\pos\Entity\ChessPosition $position */
    $position = ChessPosition::create()
      ->setBoard($board)
      ->setCastling($castling)
      ->setEnPassantSquare($en_passant_square)
      ->setLabel($label)
      ->setDescription($description);
    $position->save();

    /** @var \Drupal\pos\Entity\ChessPosition $saved_position */
    $saved_position = ChessPosition::load($position->id());
    $this->assertEquals($board, $saved_position->getBoard());
    $this->assertEquals($castling, $saved_position->getCastling());
    $this->assertEquals($en_passant_square, $saved_position->getEnPassantSquare());
    $this->assertEquals($label, $saved_position->getLabel());
    $this->assertEquals($description, $saved_position->getDescription());
  }

  public function testGetPositionLabels(){
    $position1 = ChessPosition::create()
      ->setBoard('4k3/8/8/2p5/8/8/2P5/4K5')
      ->setLabel('Pawn promotion position');
    $position1->save();
    $position2 = ChessPosition::create()
      ->setBoard('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR')
      ->setLabel('Default Value');
    $position2->save();
    $position3 = ChessPosition::create()
      ->setBoard('k7/4P3/8/8/8/8/8/K7')
      ->setLabel('New board');
    $position3->save();

    $game_position = ChessPosition::getPositionLabels();
    $this->assertEquals($game_position, [
        $position1->getBoard() => $position1->getLabel(),
        $position2->getBoard() => $position2->getLabel(),
        $position3->getBoard() => $position3->getLabel(),
      ]);
  }

}
