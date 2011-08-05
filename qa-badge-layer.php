<?php

	class qa_html_theme_layer extends qa_html_theme_base {

	// theme replacement functions

		function head_script() {
			qa_html_theme_base::head_script();
			$this->output("
			<script>".(qa_opt('badge_notify_time') != '0'?"
				$('document').ready(function() { $('.notify-container').delay(".((int)qa_opt('badge_notify_time')*1000).").fadeOut(); });":"")."
				function badgeEdit(slug,end) {
					if(end) {
						$('#badge_'+slug+'_edit').hide();
						$('#badge_'+slug+'_badge').show();
						$('#badge_'+slug+'_badge').html($('#badge_'+slug+'_edit').val());
						return;
					}
					$('#badge_'+slug+'_badge').hide();
					$('#badge_'+slug+'_edit').show();
					$('#badge_'+slug+'_edit').focus();
				}
			</script>");
		}
		function head_css()
		{
			qa_html_theme_base::head_css();
			$this->output('
			<style>
				.notify-container {
					left: 0;
					right: 0;
					top: 0;
					padding: 0;
					position: fixed;
					width: 100%;
					z-index: 10000;
				}
					.badge-notify {
					background-color: #F6DF30;
					color: #444444;
					font-weight: bold;
					width: 100%;
					text-align: center;
					font-family: sans-serif;
					font-size: 14px;
					padding: 10px 0;
					position:relative;
				}
				.notify-close {
					color: #735005;
					cursor: pointer;
					font-size: 18px;
					line-height: 18px;
					padding: 0 3px;
					position: absolute;
					right: 8px;
					text-decoration: none;
					top: 8px;
				}				
				.badge-table {
				}
				.badge-bronze,.badge-silver, .badge-gold {
					margin-right:4px;
					cursor:pointer;
					color: #000;
					font-weight:bold;
					text-align:center;
					border-radius:4px;
					width:120px;
					padding: 0 10px;
					display: inline-block;
				}
				.badge-bronze {
					background-color: #CB9114;
					border:2px solid #6C582C;
				}				
				.badge-silver {
					background-color: #CDCDCD;
					border:2px solid #737373;
				}				
				.badge-gold {
					background-color: #EEDD0F;
					border:2px solid #5F5908;
				}				
				.badge-bronze-medal, .badge-silver-medal, .badge-gold-medal  {
					cursor:pointer;
					font-size: 14px;
					font-family:sans-serif;
				}
				.badge-bronze-medal {
					color: #CB9114;
				}				
				.badge-silver-medal {
					color: #CDCDCD;
				}				
				.badge-gold-medal {
					color: #EEDD0F;
				}				
				.badge-desc {
					padding-left:8px;
				}			
			</style>');
		}

		function body_prefix()
		{
			qa_html_theme_base::body_prefix();
			$this->badge_notify();
			
		}

		function body_suffix()
		{
			qa_html_theme_base::body_suffix();
			if($this->content['test-notify'])	$this->trigger_notify('Congratulations!  This is a test message');
		}

		function form_body($form)
		{
			qa_html_theme_base::form_body($form);

			if((bool)qa_opt('badge_admin_user_field') && preg_match('/^\.\.\/user\//',qa_self_html())) { // <- add user badge list
				$this->user_badge_form();
			}
		}

		function post_meta_who($post, $class)
		{
			if((bool)qa_opt('badge_admin_user_widget') && isset($post['who'])) {
				$handle = preg_replace('/<[^>]+>/','',$post['who']['data']); // this gets the 'who', not necessarily the post userid!
				if (isset($post['who']['points'])) {
					$post['who']['points']['data'] = $this->user_badge_widget($handle).'&nbsp;'.$post['who']['points']['data'];
				}
				else if (isset($post['who']['title'])) {
					$post['who']['title'] = $post['who']['title'].'&nbsp;'.$this->user_badge_widget($handle);
				}
			}
			qa_html_theme_base::post_meta_who($post, $class);
		}
		
		function q_view_main($q_view) {
			qa_html_theme_base::q_view_main($q_view);
			
			// badge check on view update
			
			if(isset($this->content['inc_views_postid'])) {
				$oid = $content['inc_views_postid'];
				$uid = $content['raw']['userid'];
				$views = qa_db_read_one_value(
					qa_db_query_sub(
						'SELECT views FROM ^posts WHERE postid=# ',
						$oid
					),
					true
				);
				$views++; // because we haven't incremented the views yet
				
				$badges = array('notable_question','popular_question','famous_question');

				foreach($badges as $badge_slug) {
					if($views  >= (int)qa_opt('badge_'.$badge_slug.'_var') && qa_opt('badge_'.$badge_slug.'_enabled') !== '0') {
						$result = qa_db_read_one_value(
							qa_db_query_sub(
								'SELECT badge_slug FROM ^userbadges WHERE user_id=# AND badge_slug=$ AND object_id=#',
								$uid, $badge_slug, $oid
							),
							true
						);
						
						if (!$result) { // not already awarded this badge for this question
							$this->award_badge($oid, $uid, $badge_slug);
						}
					}
				}	
			}
		}

	// worker functions

		function award_badge($object_id, $user_id, $badge_slug) {
			
			// add badge to userbadges
			
			qa_db_query_sub(
				'INSERT INTO ^userbadges (awarded_at, notify, object_id, user_id, badge_slug, id) '.
				'VALUES (NOW(), #, #, #, #, 0)',
				0, $object_id, $user_id, $badge_slug
			);
			
		}

		function badge_notify() {
			$userid = qa_get_logged_in_userid();
			
			$result = qa_db_read_all_values(
				qa_db_query_sub(
					'SELECT badge_slug FROM ^userbadges WHERE user_id=# AND notify=1',
					$userid
				)
			);
			if(count($result) > 0) {

				$notice = '<div class="notify-container">';
				
				// populate notification list
				foreach($result as $slug) {
					$badge_name=qa_badge_lang('badges/'.$slug);
					if(!qa_opt('badge_'.$slug.'_name')) qa_opt('badge_'.$slug.'_name',$badge_name);
					$name = qa_opt('badge_'.$slug.'_name');
					
					$notice .= '<div class="badge-notify notify">'.qa_badge_lang('badges/badge_notify')."'".$name.'\'!<div class="notify-close" onclick="$(this).parent().hide(\'slow\')">x</div></div>';
				}

				$notice .= '</div>';
				
				// remove notification flag
				
				qa_db_query_sub(
					'UPDATE ^userbadges SET notify=0 WHERE user_id=# AND notify=1',
					$userid
				);
			}
			$this->output($notice);
		}
		
		function trigger_notify($message) {
			$notice = '<div class="notify-container"><div class="badge-notify notify">'.$message.'<div class="notify-close" onclick="$(this).parent().fadeOut()">x</div></div></div>';
			$this->output($notice);
		}
		
		function priviledge_notify() { // gained priviledge
		}
		
		function user_badge_widget($handle) {
			
			// displays small badge widget, suitable for meta
			
			$userid = $this->getuserfromhandle($handle);
			
			if(!$userid) return;

			$result = qa_db_read_all_assoc(
				qa_db_query_sub(
					'SELECT ^badges.badge_type,COUNT(^userbadges.id) FROM ^badges,^userbadges WHERE ^badges.badge_slug=^userbadges.badge_slug AND ^userbadges.user_id=# GROUP BY ^badges.badge_type ORDER BY ^badges.badge_type',
					$userid
				)
			);

			if(count($result) == 0) return;

			$output='<span id="badge-medals-widget">';
			for($x = 2; $x >= 0; $x--) {
				$a = $result[$x];
				$count = $a['COUNT('.QA_MYSQL_TABLE_PREFIX.'userbadges.id)'];
				if($count == 0) continue;

				$type = qa_get_badge_type($x);
				$types = $type['slug'];
				$typed = $type['name'];

				$output.='<span class="badge-'.$types.'-medal" title="'.$count.' '.$typed.'">●</span><span class="badge-'.$types.'-count" title="'.$count.' '.$typed.'"> '.$count.'</span> ';
			}
			$output = substr($output,0,-1);  // lazy remove space
			$output.='</span>';
			return($output);
		}

		function user_badge_form() {

			// displays badge list in user profile
			
			$handle = preg_replace('/^\.\.\/user\/([^\/]+)\/*$/',"$1",qa_self_html());

			$userid = $this->getuserfromhandle($handle);
			if(!$userid) return;

			$result = qa_db_read_all_assoc(
				qa_db_query_sub(
					'SELECT ^badges.badge_slug, ^badges.badge_type FROM ^badges,^userbadges WHERE ^badges.badge_slug=^userbadges.badge_slug AND ^userbadges.user_id=#',
					$userid
				)
			);
			
			if(count($result) == 0) return;
			
			$output = '
		<h2>Badges</h2>
		<table class="qa-form-wide-table">
			<tbody>';
			// count badges
			
			$badges;
			
			foreach($result as $info) {
				$type = $info['badge_type'];
				$slug = $info['badge_slug'];
				if(isset($badges[$type][$slug])) $badges[$type][$slug]++;
				else $badges[$type][$slug] = 1;
				
			}
			
			foreach($badges as $type => $badge) {
				foreach($badge as $slug => $count) {
					$badge_name=qa_badge_lang('badges/'.$slug);
					if(!qa_opt('badge_'.$slug.'_name')) qa_opt('badge_'.$slug.'_name',$badge_name);
					$name = qa_opt('badge_'.$slug.'_name');
					
					$var = qa_opt('badge_'.$slug.'_var');
					$desc = str_replace('#',$var,qa_badge_lang('badges/'.$slug.'_desc'));
					
					$typea = qa_get_badge_type($type);
					$types = $typea['slug'];
					$typed = $typea['name'];
					
					$output .= '
					<tr>
						<td class="qa-form-wide-label">
							<span class="badge-'.$types.'" title="'.$desc.' ('.$typed.')">'.$name.'</span>
						</td>
						<td class="qa-form-wide-data">
							<span class="badge-count">x&nbsp;'.$count.'</span>
						</td>
					</tr>';
				}
			}
			$output .= '
			</tbody>
		</table>';
			$this->output($output);
		}

		function admin_badge_button() {
			$this->output('<form METHOD="POST" ACTION="../qa-plugin/badges/qa-badge-recalc.php"><input type="submit" onmouseout="this.className=\'qa-form-basic-button qa-form-basic-button-check_badges\';" onmouseover="this.className=\'qa-form-basic-hover qa-form-basic-hover-check_badges\';" class="qa-form-basic-button qa-form-basic-button-check_badges" title="" value="Check Badges" onclick="return qa_check_badges_click(this.name, this, \'Stop Checking\', \'check_badges_note\');" name="docheckbadges"></form>');
		}
		function getuserfromhandle($handle) {
			require_once QA_INCLUDE_DIR.'qa-app-users.php';
			
			if (QA_FINAL_EXTERNAL_USERS) {
				$publictouserid=qa_get_userids_from_public(array($handle));
				$userid=@$publictouserid[$handle];
				
			} 
			else {
				$userid = qa_db_read_one_value(
					qa_db_query_sub(
						'SELECT userid FROM ^users WHERE handle = $',
						$handle
					),
					true
				);
			}
			if (!isset($userid)) return;
			return $userid;
		}
		
	}
	
