/* DOKUWIKI:include_once vendor/pnotify/jquery.pnotify.js */
/**
 * DokuWiki Plugin timetrack (JavaScript Component) 
 *
 * Plugin timetrack
 * 
 * @author     peterfromearth <coder@peterfromearth.de>
 *
 */
jQuery.pnotify.defaults.styling = "jqueryui";
jQuery.pnotify.defaults.delay = 2000;
jQuery.pnotify.defaults.history = false;

jQuery(function() {
	jQuery(document).on("click","li.plugin_timetrack a",function(e) {
		e.preventDefault();
		jQuery(this).blur();
		if(!jQuery('#timetrack__dialog').length){
	        jQuery('body').append('<div id="timetrack__dialog" position="absolute" border=1 height="800px"><div><div class="ui-tabs-panel"></div></div></div>');
	        jQuery( "#timetrack__dialog" ).dialog({title:LANG.plugins.timetrack['timetrack'],
	            height:Math.min(700,jQuery(window).height()-50),
	            width: Math.min(700,jQuery(window).width()-50),
	            autoOpen:true,
	            modal:true,
	           
	        });
	        
	    }
		jQuery("#timetrack__dialog").dialog("open");
		timetrack_loadDialog('current');
		
	});
	
	jQuery(document).on('click','#timetrack-form button',function(event){
		event.preventDefault();
		var $btn = jQuery(event.target);

		timetrack_loadDialog(jQuery('#timetrack-form input[name=cmd]').val(),{act:$btn.attr('name'),yearweek:$btn.attr('value')});

	});
	
	jQuery(document).on('click','#timetrack-form input',function(event){
		this.select();
	});
	jQuery(document).on('change keyup','#timetrack-form input',function(event){
		var $input = jQuery(this);
		
		if($input.val() *1 < 0) $input.val(0);
		
		var date = $input.data('date');
		
		var $dateInputs = jQuery('#timetrack-form input[data-date="'+date+'"]');
		
		var date_sum = 0;
		$dateInputs.each(function(e){
			date_sum += parseInt(jQuery(this).val() * 1);
		});
		
		var $th = jQuery('#timetrack-form th[data-date="'+date+'"]');

		$th.text($th.data('otherhours')*1 + date_sum);
		
	});
	jQuery(document).on('keyup','#timetrack-form input',function(event){
		var $input = jQuery(this);
		var $input_next;
		var pos;
		switch(event.which) {
	        case 37: // left
	        	$input_next = $input.closest('td').prev().find('input');
	        	if(!$input_next.length) {
	        		$input_next = $input.closest('tr').prev().find('input').last();
	        	}
	        	$input_next.focus().select();
	        	break;
	
	        case 38: // up
	        	pos = $input.closest('tr').find('input').index($input);

	        	$input_next = jQuery($input.closest('tr').prev().find('input')[pos]);
	        	if($input_next.length) {
	        		$input_next.focus().select();
	        	}
	        	break;
	
	        case 39: // right
	        	$input_next = $input.closest('td').next().find('input');
	        	if(!$input_next.length) {
	        		$input_next = $input.closest('tr').next().find('input').first();
	        	}
	        	$input_next.focus().select();
	        	break;
	
	        case 40: // down
	        	pos = $input.closest('tr').find('input').index($input);

	        	$input_next = jQuery($input.closest('tr').next().find('input')[pos]);
	        	if($input_next.length) {
	        		$input_next.focus().select();
	        	}
	        	break;
	
	        default: return; // exit this handler for other keys
	    }
		event.preventDefault(); // prevent the default action (scroll / move caret)
		
	});
	
	function timetrack_loadDialog(cmd,data) {
		jQuery.ajax({
			type:'POST',
			url:DOKU_BASE + 'lib/exe/ajax.php',
		    data:{ call: 'plugin_timetrack', cmd: cmd, pageid:JSINFO.id, data:data, sectok:JSINFO.sectok },
		    success:timetrack_result,
		    beforeSend:function() {jQuery('#timetrack__dialog').addClass("loading");}
		});
	}
	
	function timetrack_save(applyonly) {
		var $form = jQuery('#timetrack-form');
		
		jQuery.ajax({
			type:'POST',
			url:DOKU_BASE + 'lib/exe/ajax.php',
		    data:$form.serialize(),
		    success:applyonly ? timetrack_resultapply : timetrack_resultnoapply,
		    beforeSend:function() {jQuery('#timetrack__dialog').addClass("loading");}
		});
	}
	
	function timetrack_resultapply(data) {
		timetrack_result(data,true);
	}
	function timetrack_resultnoapply(data) {
		timetrack_result(data,false);
	}
	function timetrack_result(data, applyonly) {
		if(!data) {
			console.log('timetrack returns false');
			return false;
		}
		var data = JSON.parse(data);
		
		if(data.dialog) {
			jQuery("#timetrack__dialog > div").empty();
	        jQuery("#timetrack__dialog > div").html(data.dialog);
	        jQuery("#timetrack-dialog-tabs").tabs({
	        	beforeActivate: function( event, ui ) {
	        		event.preventDefault();
	        		timetrack_loadDialog(ui.newTab.data('tabid'));
	        	},
	        	active:jQuery("#timetrack-dialog-tabs ul a[selected=selected]").data('index')
	        });
	        if(data.cmd === 'current' || data.cmd === 'recent') {
	        	jQuery("#timetrack__dialog").dialog({
			        buttons:[
		                {text:LANG.plugins.timetrack['closeDialog'],icons: {primary:'ui-icon-close'}, class: 'left-button' ,click: function() {jQuery(this).dialog('close');}},
		                {text:LANG.plugins.timetrack['save'],icons: {primary:'ui-icon-check'},click: function() {timetrack_save(false);}},
		                {text:LANG.plugins.timetrack['apply'],icons: {primary:'ui-icon-refresh'},click: function() {timetrack_save(true);}},
		                ], 
			    });
				jQuery('#timetrack-form input[data-date="'+new Date().toISOString().slice(0, 10)+'"]').first().select().focus();

			} else if(data.cmd === 'overview') {
	        	jQuery("#timetrack__dialog").dialog({
			        buttons:[
				         {text:LANG.plugins.timetrack['closeDialog'],click: function() {jQuery(this).dialog('close');}},

			        ],
			    });
			}       
	        jQuery("#timetrack__dialog").dialog("open");
	        jQuery('#timetrack__dialog').removeClass('loading');
	        

		} 
		
		if(data.success ==  true) {
			if(applyonly !== true) jQuery("#timetrack__dialog").dialog("close");
			
			 jQuery.pnotify({
		            title: false,
		            text: data.msg?data.msg:'gespeichert',
		            type: 'success',
		            icon: false,
		            delay: data.notifyDelay?data.notifyDelay*1000:2000,
		            animate_speed: 100,
		            animation: {
		                effect_in:'bounce',
		                effect_out:'drop',
		            }
		        });
		}
		
	}
});
