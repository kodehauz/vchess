
var moveIdx=new Array(-1,-1); // [0] is Src, [1] is Dst
function highlightMove(cmd)
{
theme="default";
	/* Clear old highlighting */
	for (i=0;i<2;i++)
		if (moveIdx[i]!=-1) {
			x=moveIdx[i]%8;
			y=parseInt(moveIdx[i]/8);
			if ((y+1+x)%2==0)
				img="wsquare.jpg";
			else
				img="bsquare.jpg";
			obj=window.document.getElementById("btd"+moveIdx[i]);
			if (obj)
				obj.style.backgroundImage="url(/"+module_path+"/images/"+theme+"/"+img+")";
			moveIdx[i]=-1;
		}
	
	/* If command is empty don't highlight again */
	if (cmd==null || cmd=="")
		return;

	/* Parse command for source/destination and highlight it */
	moveIdx[0]=(cmd.charCodeAt(2)-49)*8+(cmd.charCodeAt(1)-97);
	if (cmd.length>=6)
		moveIdx[1]=(cmd.charCodeAt(5)-49)*8+(cmd.charCodeAt(4)-97);
	else
		moveIdx[1]=-1;

	/* Set new highlighting */
	for (i=0;i<2;i++)
		if (moveIdx[i]!=-1) {
			x=moveIdx[i]%8;
			y=parseInt(moveIdx[i]/8);
			if ((y+1+x)%2==0)
				img="whsquare.jpg";
			else
				img="bhsquare.jpg";
			obj=window.document.getElementById("btd"+moveIdx[i]);
			if (obj)
				obj.style.backgroundImage="url(/"+module_path+"/images/"+theme+"/"+img+")";
		}
}

function checkMoveButton()
{
var cform = window.document.getElementById("vchess-commandForm");

	if (cform && window.document.getElementById("edit-moveButton")) {
		if (cform.move.value.length >= 6)
			window.document.getElementById("edit-moveButton").disabled=false;
		else
			window.document.getElementById("edit-moveButton").disabled=true;
	}
}

/* Assemble command into commandForm.move and submit move if destination is
 * clicked twice. */
function assembleCmd(part)
{

	var cform = window.document.getElementById("vchess-commandForm");	
	var cmd = cform.move.value;
	if (cmd == part)
		cform.move.value = "";
	else if (cmd.length == 0 || cmd.length >= 6) {
		if (part.charAt(0) != '-' && part.charAt(0) != 'x')
			cform.move.value = part;
		else if (cmd.length >= 6 && cmd.substring(3,6)==part) {
			if (confirm("Execute move "+cmd+"?"))
				onClickMove();
		}
	} else if (part.charAt(0) == '-' || part.charAt(0) == 'x')
		cform.move.value = cmd + part;
	else
		cform.move.value = part;
	highlightMove(cform.move.value);
	checkMoveButton();
	return false;
}

function onClickMove()
{
var cform = window.document.getElementById("vchess-commandForm");
	if (cform.move.value!="") {
		var move=cform.move.value;
		/* If pawn enters last line ask for promotion */
		if (move[0]=='P' && (move[5]=='8' || move[5]=='1')) {
			if (confirm('Promote to Queen? (Press Cancel for other options)'))
				move=move+'Q';
			else if (confirm('Promote to Rook? (Press Cancel for other options)'))
				move=move+'R';
			else if (confirm('Promote to Bishop? (Press Cancel for other options)'))
				move=move+'B';
			else if (confirm('Promote to Knight? (Press Cancel to abort move)'))
				move=move+'N';
			else
				return;
		}
		cform.cmd.value=move;
		gatherCommandFormData();
		cform.submit();
	}
}


function gatherCommandFormData() 
{
	fm=window.document.getElementById("vchess-commandForm");
//	if (document.commentForm && document.commentForm.comment)
//		fm.comment.value=document.commentForm.comment.value;
//	if (document.pnotesForm && document.pnotesForm.privnotes)
//		fm.privnotes.value=document.pnotesForm.privnotes.value;
//	else
//		fm.privnotes.disabled=true;
}