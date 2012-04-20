<?php

define('INITIAL_RATING', 1200);
define('MIN_RATED_GAMES', 5);
define('RATING_CHANGE_MULTIPLIER', 25);

/**
 * @file
 * Functions to apply game results to rating according to ELO's formula
 */

/**
 * Compute initial rating based on average opponent strength
 */
// function vchess_get_initial_rating($uid) {
//   $player = new Player($uid);

//   if ($player->played() == 0) {
//     $rating = INITIAL_RATING;
//   }

// //  $rating = $avg_opp_rating + 700 * (($wins + 0.5 * $draws) / $played -0.5) * $played / ($played + 2);

//   return $rating;
// }

/**
 * Get probability for player to win from difference in rating
 * 
 * @param $diff
 *   Difference between two players ratings
 *   
 * @return 
 *   Probability, between 0 and 1, that the player would win 
 */
function gamer_get_win_probability($diff) {
  $absdiff = abs($diff);
  if ($diff > 735) {
    $probability = 1;
  }
  elseif ($diff < -735) {
    $probability = 0;
  }
  else {
    $probability = 0.5
      + 1.4217 * 0.001 * $diff
      - 2.4336 * 0.0000001 * $diff * $absdiff
      - 2.5140 * 0.000000001 * $diff * $absdiff * $absdiff
      + 1.9910 * 0.000000000001 * $diff * $absdiff * $absdiff * $absdiff;
  }

  return $probability;
}

/**
 * Get rating change multiplier (coefficient K) which is used to scale expected propability before
 * updating the rating.
 */
function gamer_get_rating_change_multiplier() {
//   if ($rating < 2000) {
//     $K = 30;
//   }
//   elseif ($rating > 2400) {
//     $K = 10;
//   }
//   else {
//     $K = 130 -$rating / 20;
//   }
//   return $K;

  return RATING_CHANGE_MULTIPLIER;
}

/**
 * Update user stats and return modified stats. Score is:
 * - 1 (user won),
 * - 0.5 (user drew)
 * - 0 (user lost)
 *
 * The rating is updated every five games.
 * Modification in between is stored in rating change. If less than five games
 * are finished, sum up opponent strength in rating change. If five games are
 * finished compute initial rating based on wdl and average opponent strength.
 * Otherwise store base change (result-expected) in rating change.
 * Computation is done by Elo's formula. Thus to get real change of rating
 * rating change is multiplied by coefficient K which depends on the current
 * rating (<2000?30:>2400?10:130-R/20). Initial rating and expected result based
 * on player strength is computed by Elo, too.
 *
 * The final rating change is truncated to four digits after the comma.
 *
 *
 */
function gamer_get_updated_stats($uid, $ustats, $ostats, $score) {
  $played = $ustats['won'] + $ustats['drawn'] + $ustats['lost'];

  // To update user rating of opponent is required. If none is given yet
  // (<5 games) use initial elo formula to get a temporary value or assume
  // 1200 if none finished yet.
  $rating = $ostats['rating'];
  if ($rating == 0) {
    if ($ostats['played'] > 0) {
      $rating = vchess_get_init_rating($ostats['won'], $ostats['drawn'],
        $ostats['lost'], $ostats['rchange'] / $ostats['played']);
    }
    else {
      $rating = INITIAL_RATING;
    }
  }

  // Update wins/draws/losses
  if ($score == 1) {
    $ustats['won']++;
  }
  elseif ($score == 0.5) {
    $ustats['drawn']++;
  }
  else {
    $ustats['lost']++;
  }

  // Update rating change
//  if ($played < 5) {
    // No rating yet so store strength of opponent
//    $ustats['played']++;
//    $ustats['rchange'] += $rating;
//  }
//  else {

  // Update rating change according to the winning probability
  $win_probability = gamer_get_win_probability($ustats['rating'] - $rating);
  $ustats['played']++;
  $ustats['rchange'] = round(gamer_get_rating_change_multiplier() * ($score - $win_probability));

  // Update rating/get initial rating if five games are finished.
  // Use actual number of w+d+l for this.
//   if ($ustats['played'] < MIN_RATED_GAMES) {
//     // Get initial rating from the first five games
//       $ustats['rating'] = vchess_get_init_rating($uid);
//   }
//   else {
//     // Update rating
//     $ustats['rating'] += round($ustats['rchange']);
//   }

  // Update rating
  $ustats['rating'] += $ustats['rchange'];

  // Truncate rating change to four digits after comma.
//  $ustats['rchange'] = sprintf('%.4f', $ustats['rchange']);

  return $ustats;
}

/**
 * Update stats based on a win
 * 
 * @param $winner
 *   The player who won
 * @param $loser
 *   The player who lost
 */
function gamer_update_stats_win(Player $winner, Player $loser) {
  // Load stats. Is always successful (returns zero array if not found).
  // Old format is also updated, see compatiblity info in io.php.
  $winner_stats = gamer_load_user_stats($winner->uid);
  $loser_stats = gamer_load_user_stats($loser->uid);

  // Get the new stats
  $winner_stats_new = gamer_get_updated_stats($winner->uid, $winner_stats, $loser_stats, 1);
  $loser_stats_new = gamer_get_updated_stats($loser->uid, $loser_stats, $winner_stats, 0);

  // Save changes
  gamer_save_user_stats($winner->uid, $winner_stats_new);
  gamer_save_user_stats($loser->uid, $loser_stats_new);
}


/**
 * Update stats of both users according to result (w,b,-)
 *
 * @param $white
 *   Uid of white player
 * @param $black
 *   Uid of black player
 * @param $result
 *   Result is either "-" (draw), "w" (white win), "b" (black win)
 */
function gamer_update_stats($white, $black, $result) {
  // Load stats. Is always successful (returns zero array if not found).
  $wstats = gamer_load_user_stats($white);
  $bstats = gamer_load_user_stats($black);

  // Translate result to score of White and update both players
  if ($result == '-') {
    $score = 1;
  }
  elseif ($result == 'w') {
    $score = 2;
  }
  else {
    $score = 0;
  }
  $wstats_new = gamer_get_updated_stats($wstats, $bstats, $score);
  $bstats_new = gamer_get_updated_stats($bstats, $wstats, 1 - $score);

  // Save changes
  gamer_save_user_stats($white, $wstats_new);
  gamer_save_user_stats($black, $bstats_new);
}
