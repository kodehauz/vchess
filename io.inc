<?php// $id$

/* Convert board coordinate [a-h][1-8] to 1dim index [0..63] */
function vchess_bc2i($coord)
{
  switch ($coord[0]) {
    case 'a': $x=0; break;
    case 'b': $x=1; break;
    case 'c': $x=2; break;
    case 'd': $x=3; break;
    case 'e': $x=4; break;
    case 'f': $x=5; break;
    case 'g': $x=6; break;
    case 'h': $x=7; break;
    default: return 64; /* error code */
  }
  $y=$coord[1]-1; 
  if($y<0 || $y>7 )
    return 64; /* error code */
  $index=$y*8+$x;
  return $index;
}

/* Convert index [0..63] to board coordinate [a-h][1-8] */
function vchess_i2bc($index)
{
  if($index<0 || $index>63)
    return '';
  $y=floor($index/8)+1;
  $x=chr(($index%8)+97);
  $coord=$x.$y;
  return $coord;
}


/* Get full name of chessman from chessman identifier. */
function vchess_getCMName($short)
{
  $name='empty';
  switch ($short) {
    case 'P': $name='Pawn'; break;
    case 'R': $name='Rook'; break;
    case 'N': $name='Knight'; break;
    case 'B': $name='Bishop'; break;
    case 'K': $name='King'; break;
    case 'Q': $name='Queen'; break;
  }
  return $name;
}

/* Get difference in chessmen for each class (pawn=0,queen=5).
 * Positive value = White has more, Negative value = Black has more. */
function vchess_getCMDiff($board)
{
	$diff = array(0,0,0,0,0);
	for ($i=0;$i<64;$i++) {
		switch ($board[$i]) {
			case 'wP': $diff[0]++; break;
			case 'wN': $diff[1]++; break;
			case 'wB': $diff[2]++; break;
			case 'wR': $diff[3]++; break;
			case 'wQ': $diff[4]++; break;
			case 'bP': $diff[0]--; break;
			case 'bN': $diff[1]--; break;
			case 'bB': $diff[2]--; break;
			case 'bR': $diff[3]--; break;
			case 'bQ': $diff[4]--; break;
		}
	}
	for ($i=0;$i<5;$i++)
		if ($diff[$i] != 0)
			return $diff;
	return null;
}
/* Load game info and return it as array. If $location is not null it is either
 * 'opengames' or 'archive'. If set only that one location is checked. Return 
 * null if error occured.
 * archived: resides in archive
 * ts_start: timestamp of starting date (secs)
 * ts_last: timestamp of last move (secs)
 * white: name of white player
 * black: name of black player
 * curmove: number of current move (start at 0)
 * curplyr: color of current player (w or b)
 * curstate: state of game (w/b=white/black won,-=draw,D=draw offered,?=open) */
function vchess_ioLoadGameInfo($location,$gid)
{

$sql = "SELECT timestamps, white, black, state FROM {vchess_games} WHERE gid = '".$gid."'";
$res = db_query($sql);
$data = db_fetch_object($res);


	$game=array();

	$game['archived']=0;

if(!data)return null;
	$game['gid']=$gid;
	/* Build time stamps */
	$aux=explode(' ',trim($data->timestamps));
	$game['ts_start']=mktime($aux[3],$aux[4],0,$aux[1],$aux[2],$aux[0]);
	$game['ts_last']=mktime($aux[8],$aux[9],0,$aux[6],$aux[7],$aux[5]);

	/* Parse header */
	$hdr=explode(' ',trim($data->state));
	$game['white']=$data->white;
	$game['black']=$data->black;
	$game['curmove']=$hdr[0];
	$game['curplyr']=$hdr[1];
	$game['curstate']=$hdr[2];

	return $game;
}

/* Load array of game infos according to the given filter criteria. null for a
 * criteria means any value. $location must be set either 'opengames' or 
 * 'archive'.
 * Always returns an array but it may be empty if no matching games were found 
 * and entries are sorted most recent first. */
function vchess_compareTimestamp($a,$b)
{
	if ($a['ts_last']==$b['ts_last'])
		return 0;
	else if ($a['ts_last']<$b['ts_last'])
		return 1;
	else
		return -1;
}
function vchess_ioLoadGameInfoList($location,$player,$pcolor,$opponent)
{


$sql = "SELECT gid FROM {vchess_games}";
$res = db_query($sql);

	
$infos=array();
while ($data = db_fetch_object($res)) {
	$entry = $data->gid;
		if ($player!=null || $opponent!=null) {
			if ($opponent!=null && 
					strpos($entry,'-'.$opponent.'-')===false)
				continue;
			if ($player!=null) {
				if (strpos($entry,'-'.$player.'-')===false)
					continue;
				if ($pcolor!=null) {
					$fields=explode('-',$entry);
					if ($pcolor=='w' && $fields[1]!=$player)
						continue;
					else if ($pcolor=='b' && $fields[2]!=$player)
						continue;
				}
			}
		}
		$infos[$entry]=vchess_ioLoadGameInfo($location,$entry);
}

	

	/* NB: sort will destroy index key, therefore $infos['gid'] is used
	 * later instead. */
	if (count($infos)>0)
		usort($infos,'vchess_compareTimestamp');
	return $infos;
}

/* Save an open game and update last move time stamp to current time. Chatter
 * must have been updated already (newest is first in list). */
function vchess_ioSaveGame($game,$gid)
{
$timestamps = date('Y m d H i ',$game['ts_start']).date('Y m d H i',time());
$state = $game['curmove'].' '.$game['curplyr'].' ';
$state.=$game['curstate'].' '.$game['wcs'].' '.$game['wcl'].' '.$game['bcs'].' '.$game['bcl'].' '.$game['w2spm'].' ';
$state.=$game['b2spm'].' '.$game['lastmove'].' '.$game['lastkill'].' '.$game['oscf'].' '.$game['olcf'];
$board_white='';
$board_black='';

for ($i=0;$i<64;$i++)
	if ($game['board'][$i]!='' && $game['board'][$i][0]=='w') {
		$c=vchess_i2bc($i);
		$board_white.=$game['board'][$i][1].$c.' ';
	}
for ($i=0;$i<64;$i++)
	if ($game['board'][$i]!='' && $game['board'][$i][0]=='b') {
		$c=vchess_i2bc($i);
		$board_black.=$game['board'][$i][1].$c.' ';
	}

$sql = "SELECT gid FROM {vchess_games} WHERE gid = '".$gid."'";
$res = db_query($sql);
$out = db_fetch_object($res);

if(!$out)
db_query('INSERT INTO {vchess_games} (gid, timestamps, white, black, state, board_white, board_black) '."VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s')",$gid,$timestamps,$game['white'],$game['black'],$state,$board_white,$board_black);
else
db_query('UPDATE {vchess_games} '." SET timestamps = '%s', state = '%s', board_white = '%s', board_black = '%s' WHERE gid = '%s'",$timestamps,$state,$board_white,$board_black,$gid);


}

/* Create a new game. If $bcomment is not empty it is black's initial comment.
 * Return name of game. */
function vchess_ioCreateGame($white,$black,$bcomment)
	{
	/* Build new game context */ 
	$game=array();
	$game['ts_start']=time();
	$game['white']=$white;
	$game['black']=$black;
	$game['curmove']=0;
	$game['curplyr']='w';
	$game['curstate']='?';
	$game['wcs']=1;
	$game['wcl']=1;
	$game['bcs']=1;
	$game['bcl']=1;
	$game['w2spm']='x';
	$game['b2spm']='x';
	$game['lastmove']='x';
	$game['lastkill']='x';
	$game['oscf']='x';
	$game['olcf']='x';
	$game['board']=array(
		'wR', 'wN', 'wB', 'wQ', 'wK', 'wB', 'wN', 'wR',
		'wP', 'wP', 'wP', 'wP', 'wP', 'wP', 'wP', 'wP',
		'', '', '', '', '', '', '', '',
		'', '', '', '', '', '', '', '',
		'', '', '', '', '', '', '', '',
		'', '', '', '', '', '', '', '',
		'bP', 'bP', 'bP', 'bP', 'bP', 'bP', 'bP', 'bP',
		'bR', 'bN', 'bB', 'bQ', 'bK', 'bB', 'bN', 'bR');
	if (empty($bcomment))
		$bcomment='(silence)';
	else {
		$bcomment=str_replace("\\",'',strip_tags($bcomment));
		$bcomment=str_replace("\n",'<br>',$bcomment);
	}
	$bcomment='<u>'.$black.'</u>: '.$bcomment;
	$game['chatter']=array($bcomment);

	$gfname=sprintf('%s-%s-%s-',date('YmdHi',$game['ts_start']),$white,$black);
	vchess_ioSaveGame($game,$gfname);
	return $gfname;
}

/* Load list of all users as name=>name. */
// dummy function yet
function vchess_ioLoadUserList()
{
	$list=array();
	foreach ($passwords as $usr=>$pwd)
		$list[$usr]=$usr;
	return $list;
}	


/* Load a game (try active games first, then archived games) and set various
 * user-based variables, too. Return game as array or NULL on error.
 * Static (loaded) entries:
 * archived: resides in archive
 * ts_start: timestamp of starting date (secs)
 * ts_last: timestamp of last move (secs)
 * white: name of white player
 * black: name of black player
 * curmove: number of current move (start at 0)
 * curplyr: color of current player (w or b)
 * curstate: state of game (w/b=white/black won,-=draw,D=draw offered,?=open)
 * wcs: white may castle short
 * wcl: white may castle long
 * bcs, bcl: dito for black
 * w2spm: 2-step pawn move of white (x or a-h)
 * b2spm: dito for black
 * lastmove: last move in full notation (e.g. Pd2-d4 or x)
 * lastkill: chessman captured in last move with board index (e.g. wP08 or x)
 * oscf: old short castling flag (only set by king/rook move)
 * olcf: dito for long castling
 * board: chess board array (0=a1,63=h8) with e.g. 'bP', 'wQ' or ''
 * mhistory: move history list (w1,b1,w2,b2,...)
 * chatter: list of chatter lines (first is newest)
 * Dynamic (based on user id) entries:
 * p_maymove: whether it's player's turn (always 0 if user is not playing)
 * p_mayundo: player may undo last move
 * p_mayabort: player may abort game (first move or opponent took too long) 
 * p_mayarchive: player may move game to archive
 * p_color: player color (w=white,b=black or empty if not playing)
 * p_opponent: name of opponent (based on player color, empty if not playing)
 */
function vchess_ioLoadGame($gid,$uid)
{

$sql = "SELECT timestamps, white, black, state, board_white, board_black FROM {vchess_games} WHERE gid = '".$gid."'";
$res = db_query($sql);
$data = db_fetch_object($res);

$game=array();

$game['archived']=0;
if(!$data)return null;

	/* Build time stamps */
	$aux=explode(' ',trim($data->timestamps));
	$game['ts_start']=mktime($aux[3],$aux[4],0,$aux[1],$aux[2],$aux[0]);
	$game['ts_last']=mktime($aux[8],$aux[9],0,$aux[6],$aux[7],$aux[5]);

	/* Parse header */
	$hdr=explode(' ',trim($data->state));
	$game['white']=$data->white;
	$game['black']=$data->black;
	$game['curmove']=$hdr[0];
	$game['curplyr']=$hdr[1];
	$game['curstate']=$hdr[2];
	$game['wcs']=$hdr[3];
	$game['wcl']=$hdr[4];
	$game['bcs']=$hdr[5];
	$game['bcl']=$hdr[6];
	$game['w2spm']=$hdr[7];
	$game['b2spm']=$hdr[8];
	$game['lastmove']=$hdr[9];
	$game['lastkill']=$hdr[10];
	$game['oscf']=$hdr[11];
	$game['olcf']=$hdr[12];

	/* Fill chess board */
	$game['board']=array(
		'', '', '', '', '', '', '', '',
		'', '', '', '', '', '', '', '',
		'', '', '', '', '', '', '', '',
		'', '', '', '', '', '', '', '',
		'', '', '', '', '', '', '', '',
		'', '', '', '', '', '', '', '',
		'', '', '', '', '', '', '', '',
		'', '', '', '', '', '', '', '');
	$wcm=explode(' ',trim($data->board_white));
	foreach ($wcm as $cm)
		$game['board'][vchess_bc2i($cm[1].$cm[2])]='w'.$cm[0];
	$bcm=explode(' ',trim($data->board_black));
	foreach ($bcm as $cm)
		$game['board'][vchess_bc2i($cm[1].$cm[2])]='b'.$cm[0];


	$game['chatter']=array();

	/* Determine color and opponent */
	if ($uid==$game['black']) {
		$game['p_color']='b';
		$game['p_opponent']=$game['white'];
	} else if ($uid==$game['white']) {
		$game['p_color']='w';
		$game['p_opponent']=$game['black'];
	} else {
		$game['p_color']='';
		$game['p_opponent']='';
	}

	/* Check whether player may move/archive */
	$game['p_maymove']=0;
	$game['p_mayarchive']=0;
	if (($game['curplyr']=='w' && $uid==$game['white']) ||
			($game['curplyr']=='b' && $uid==$game['black'])) {
		if ($game['curstate']=='?' || $game['curstate']=='D')
			$game['p_maymove']=1;
		else if ($game['archived']==0)
			$game['p_mayarchive']=1;
	}

	/* Check whether player may abort */
	$game['p_mayabort']=0;
	if (!empty($game['p_color']) && $game['p_maymove']==0 &&
			($game['curstate']=='D' || $game['curstate']=='?') &&
			time()-$game['ts_last']>2419200/*four weeks*/ &&
			$game['archived']==0)
		$game['p_mayabort']=1;
	else if (!empty($game['p_color']) && $game['archived']==0 &&
			$game['p_maymove']==1 && 
			(($game['p_color']=='w' && $game['curmove']==0)||
			($game['p_color']=='b' && $game['curmove']==1)))
		$game['p_mayabort']=1;

	/* Check whether player may undo (only possible if game not finished) */
	if ($game['lastmove']!='x' && $game['archived']==0 &&
			(($game['curplyr']=='b' && $game['white']==$uid) ||
			 ($game['curplyr']=='w' && $game['black']==$uid)) &&
			($game['curstate']=='D' || $game['curstate']=='?') &&
			time()-$game['ts_last']<1200)
		$game['p_mayundo']=1;
	else
		$game['p_mayundo']=0;

	return $game;
}

/* Abort an open game. This is only possible if your opponent did not move
 * at all yet or did not move for more than four weeks. Aborting a game will
 * have NO influence on the game statistics. Return a status message. */
function vchess_ioAbortGame($gid,$uid)
{
//	global $res_games;

  $gamefolder =  variable_get('vchess_game_files_folder','vchess-data');
  $res_games = $gamefolder;

	
//	ioLock();

	$game=vchess_ioLoadGame($gid,$uid);
	if ($game==null)
		return 'ERROR: Game "'.$gid.'" does not exist!';
	if (!$game['p_mayabort'])
		return 'ERROR: You cannot abort the game!';
	unlink("$res_games/$gid");

//	ioUnlock();

	return 'Game "'.$gid.'" deleted.';
}

/* Load stats of user. Always return array but values are all zero if not found.
 * wins: number of wins
 * draws: number of draws
 * losses: number of losses
 * rating: current ELO rating
 * rgames: number of rating change games (update every 5 games)
 * rchange: base value of next rating modification
 */
function vchess_ioLoadUserStats($uid)
{

	$usr=array();
	$usr['wins']=0;
	$usr['draws']=0;
	$usr['losses']=0;
	$usr['rating']=0;
	$usr['rgames']=0;
	$usr['rchange']=0;

$sql = "SELECT wins, draws, losses, rating, rgames, rchange FROM {vchess} WHERE uid = '".$uid."'";
$res = db_query($sql);
$stat = db_fetch_object($res);

if($stat){
	$usr['wins'] = $stat->wins;
	$usr['draws'] = $stat->draws;
	$usr['losses'] = $stat->losses;
	$usr['rating'] = $stat->rating;
	$usr['rgame'] = $stat->rgame;
	$usr['rchange'] = $stat->rchange;

	$rating=vchess_getInitRating($usr['wins'],$usr['draws'],$usr['losses'],1200);
	$usr['rating']=floor($rating);
	}
return $usr;
}

/* Save user stats to file: replace if entry exists or append if it is new. */
function vchess_ioSaveUserStats($uid,$stats)
{

$sql = "SELECT uid FROM {vchess} WHERE uid = '".$uid."'";
$res = db_query($sql);
$out = db_fetch_object($res);
if(!$out)
  db_query('INSERT INTO {vchess} (uid, wins, draws, losses, rating, rgames, rchange) '."VALUES ('%s', %d, %d, %d, %d, %d, %d)",$uid,$stats['wins'],$stats['draws'],$stats['losses'],$stats['rating'],$stats['rgames'],$stats['rchange']);
else
  db_query('UPDATE {vchess} '." SET wins = '%d', draws = '%d', losses = '%d', rating = '%d', rgames = '%d', rchange = '%d' WHERE uid = '%s'",$stats['draws'],$stats['losses'],$stats['rating'],$stats['rgames'],$stats['rchange'],$uid,$stats['wins']);
}