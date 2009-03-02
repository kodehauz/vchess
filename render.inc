<?php
function vchess_renderBoard($board,$pc,$active,$gid)
{
  global $user;
  $uid = $user->name;
  //global $theme;
  $module_path = drupal_get_path('module','vchess');
  $theme = 'default';  // later add global theme	

  if(!$browse && isset($_POST['cmd']) && !empty($_POST['cmd'])) {
    $cmd=$_POST['cmd'];
    $comment=$_POST['comment'];
    $cmdres='';

    /* Lock access to games/user stats. Any other access (like login
     * history or private notes) is not locked since it is only 
     * accessible by one user which won't do it twice the same time. */
    //ioLock();

    if($cmd=='abort')
      $cmdres=vchess_ioAbortGame($gid,$uid);

    else if ($cmd=='acceptdraw')
      $cmdres=vchess_handleMove($gid,$uid,'accept_draw',$comment);
    else if ($cmd=='refusedraw')
      $cmdres=vchess_handleMove($gid,$uid,'refuse_draw',$comment);

    else /* try as chess move */
      $cmdres=vchess_handleMove($gid,$uid,$cmd,$comment);
      drupal_set_message($cmdres,'error');

}


  /* show white at bottom if not playing */
  if (empty($pc))
    $pc='w';

	/* build chessboard */
	
  $content= '<TABLE class="boardFrame"><TR><TD>';
  $content.= '<TABLE class="board">';
  if($pc=='w') {
    $index=56;
    $pos_change = 1;
    $line_change = -16;
  } 
  else {
    $index=7;
    $pos_change = -1;
    $line_change = 16;
  }
  for($y=0;$y<9;$y++) {
    $content.= '<TR>';
    for ($x=0;$x<9;$x++) {
      if($y==8) {
        /* number at bottom */
        if($x>0) {
	  if( $pc == 'w' )
	    $c = chr(96+$x);
          else
            $c = chr(96+9-$x);
          $content.= '<TD align="center"><IMG height=4 src="/'.$module_path.'/images/spacer.gif"><BR><B class="boardCoord">'.$c.'</B></TD>';
	} 
	else
          $content.= '<TD></TD><TD></TD>';
      } 
      else if ($x==0) {
             /* number on the left */
             if ( $pc == 'w' )
                $i = 8-$y;
             else
               $i = $y+1;
             $content.= '<TD><B class="boardCoord">'.$i.'</B></TD><TD><IMG width=4 src="/'.$module_path.'/images/spacer.gif"></TD>';
      } 
      else {
        /* normal tile */
        if ($board) {
        $entry=$board[$index];
        $color=substr($entry,0,1);
        $name=strtolower(vchess_getCMName($entry[1]));
	}
        if((($y+1)+($x))%2==0)
          $class='boardTileWhite';
        else
          $class='boardTileBlack';
        if($board==null) {
          $content.= '<TD class="'.$class.'"><IMG name="b'.$index.'" src="/'.$module_path.'/images/'.$theme.'/empty.gif"></TD>';
        } 
        else if($name!='empty') {
          if($active) {
            if($pc!=$color)
              $cmdpart=sprintf('x%s',vchess_i2bc($index));
            else
              $cmdpart=sprintf('%s%s',$board[$index][1],vchess_i2bc($index));
            $content.= '<TD id="btd'.$index.'" class="'.$class.'"><A href="" onClick="return assembleCmd(\''.$cmdpart.'\');"><IMG border=0 src="/'.$module_path.'/images/'.$theme.'/'.$color.$name.'.gif"></A></TD>';
          } 
          else
            $content.= '<TD class="'.$class.'"><IMG src="/'.$module_path.'/images/'.$theme.'/'.$color.$name.'.gif"></TD>';
        } 
        else {
          if($active) {
            $cmdpart=sprintf('-%s',vchess_i2bc($index));
            $content.= '<TD id="btd'.$index.'" class="'.$class.'"><A href="" onClick="return assembleCmd(\''.$cmdpart.'\');"><IMG border=0 src="/'.$module_path.'/images/'.$theme.'/empty.gif"></A></TD>';
          }
          else
            $content.= '<TD class="'.$class.'"><IMG src="/'.$module_path.'/images/'.$theme.'/empty.gif"></TD>';
        }
        $index += $pos_change;
        }
    }
    $index += $line_change;
    $content.= "</TR>";
  }
  $content.= "</TABLE></TD></TR></TABLE>";
  return $content;
}

//////////////////////////////////////////////////////////////////////////
/* Render command form which contains information about players and last 
 * moves and all main buttons (shown when nescessary). The final command 
 * is mapped to hidden field 'cmd' on submission. Show previous command 
 * result $cmdres if set or last move if any. Fill move edit with $move
 * if set (to restore move when notes were saved). */
function vchess_commandForm($context,$game,$cmdres,$move){
$form['cmd'] = array(
  '#type'=>'hidden',
  '#value'=> '',
);

$form['comment'] = array(
  '#type'=>'value',
  '#value'=> '',
);

$form['privnotes'] = array(
  '#type'=>'value',
  '#value'=> '',
);


if($game['p_maymove']==1){
$form['moveButton'] = array(
  '#type'=>'submit',   //originaly button
  '#value'=> 'Move',
);}
else{
//$form['moveButton'] = array(
  //'#type'=>'submit',   //originaly button
  //'#value'=> 'Refresh',
//);
}



$form['move'] = array(
  '#type'=>'hidden',
  '#value'=> $move,
);

// abort options
return $form;
}


function vchess_renderCommandForm($game,$cmdres,$move){
$out = drupal_get_form('vchess_commandForm',$game,$cmdres,$move);
return $out;
}