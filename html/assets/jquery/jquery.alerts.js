// jQuery Alert Dialogs Plugin
//
// Version 1.1
//
// Cory S.N. LaViska
// A Beautiful Site (http://abeautifulsite.net/)
// 14 May 2009
//
// Visit http://abeautifulsite.net/notebook/87 for more information
//
// Usage:
//		jAlert( message, [title, callback] )
//		jConfirm( message, [title, callback] )
//		jPrompt( message, [value, title, callback] )
//
// History:
//
//		1.00 - Released (29 December 2008)
//
//		1.01 - Fixed bug where unbinding would destroy all resize events
//
// License:
//
// This plugin is dual-licensed under the GNU General Public License and the MIT License and
// is copyright 2008 A Beautiful Site, LLC.
//
(function($) {

	$.alerts = {

		// These properties can be read/written by accessing $.alerts.propertyName from your scripts at any time

		verticalOffset: -75,                // vertical offset of the dialog from center screen, in pixels
		horizontalOffset: 0,                // horizontal offset of the dialog from center screen, in pixels/
		repositionOnResize: true,           // re-centers the dialog on window resize
		overlayOpacity: .01,                // transparency level of overlay
		overlayColor: '#FFF',               // base color of overlay
		draggable: true,                    // make the dialogs draggable (requires UI Draggables plugin)
		okButton: '&nbsp;OK&nbsp;',         // text for the OK button
		noButton: '&nbsp;No&nbsp;',         // text for the No button
		cancelButton: '&nbsp;Cancel&nbsp;', // text for the Cancel button
		lang_ua: false,
		dialogClass: null,                  // if specified, this class will be applied to all dialogs
		types: { alert: 'Alert', confirm: 'Confirm', prompt: 'Prompt', yesno: 'YesNo' },
		icons: { info: 'alert', important: 'ja_confirm', help: 'prompt', check: 'ja_confirm_acc'},

		// Public methods

		alert: function(message, title, callback) {
			if( title == null ) title = $.alerts.lang_ua ? 'Увага' : 'Внимание';
			$.alerts._show(title, message, null, this.types.alert, this.icons.info, function(result) {
				if( callback ) callback(result);
			});
		},

		alert_err: function(message, title, callback) {
			if( title == null ) title = $.alerts.lang_ua ? 'Помилка' : 'Ошибка';
			$.alerts._show(title, message, null, this.types.alert, this.icons.important, function(result) {
				if( callback ) callback(result);
			});
		},

		confirm: function(message, title, callback) {
			if( title == null ) title = $.alerts.lang_ua ? 'Увага' : 'Внимание';
			$.alerts._show(title, message, null, this.types.confirm, this.icons.important, function(result) {
				if( callback ) callback(result);
			});
		},

		confirm_acc: function(message, title, callback) {
			if( title == null ) title = $.alerts.lang_ua ? 'Увага' : 'Внимание';
			$.alerts._show(title, message, null, this.types.confirm, this.icons.check, function(result) {
				if( callback ) callback(result);
			});
		},

		prompt: function(message, value, title, callback) {
			if( title == null ) title = $.alerts.lang_ua ? 'Питання' : 'Вопрос';
			$.alerts._show(title, message, value, this.types.prompt, this.icons.help, function(result) {
				if( callback ) callback(result);
			});
		},

		yesno: function(message, title, callback) {
			if( title == null ) title = $.alerts.lang_ua ? 'Увага' : 'Внимание';
			$.alerts._show(title, message, null, this.types.yesno, this.icons.help, function(result, cancel) {
				if( callback ) callback(result, cancel);
			});
		},

		// Private methods

		_show: function(title, msg, value, type, icon, callback) {

			$.alerts._hide();
			$.alerts._overlay('show');

			$("BODY").append(
			  '<div id="popup_container">' +
			    '<h1 id="popup_title"></h1>' +
			    '<div id="popup_content">' +
			      '<div id="popup_message"></div>' +
				'</div>' +
			  '</div>');

			if( $.alerts.dialogClass ) $("#popup_container").addClass($.alerts.dialogClass);

			// IE6 Fix
			var pos = /*($.browser.msie && parseInt($.browser.version) <= 6 ) ? 'absolute' :*/ 'fixed';

			$("#popup_container").css({
				position: pos,
				zIndex: 99999,
				padding: 0,
				margin: 0
			});

			$("#popup_title").text(title);
			$("#popup_content").addClass(icon);
			$("#popup_message").text(msg);
			$("#popup_message").html( $("#popup_message").text().replace(/\n/g, '<br />') );

			$("#popup_container").css({
				minWidth: $("#popup_container").outerWidth(),
				maxWidth: $("#popup_container").outerWidth()
			});

			$.alerts._reposition();
			$.alerts._maintainPosition(true);

			switch( type ) {
				case 'Alert':
					$("#popup_message").after('<div id="popup_panel"><input type="button" value="' + $.alerts.okButton + '" id="popup_ok" /></div>');
					$("#popup_ok").click( function() {
						$.alerts._hide();
						callback(true);
					});
					$("#popup_ok").focus().keypress( function(e) {
						if( e.keyCode == 13 || e.keyCode == 27 ) $("#popup_ok").trigger('click');
					});
				break;

				case 'Confirm':
					$("#popup_message").after('<div id="popup_panel"><input type="button" value="' + $.alerts.okButton + '" id="popup_ok" /> <input type="button" value="' + $.alerts.cancelButton + '" id="popup_cancel" /></div>');
					$("#popup_ok").click( function() {
						$.alerts._hide();
						if( callback ) callback(true);
					});
					$("#popup_cancel").click( function() {
						$.alerts._hide();
						if( callback ) callback(false);
					});
					$("#popup_ok").focus();
					$("#popup_ok, #popup_cancel").keypress( function(e) {
						if( e.keyCode == 13 ) $("#popup_ok").trigger('click');
						if( e.keyCode == 27 ) $("#popup_cancel").trigger('click');
					});
				break;

				case 'YesNo':
					$("#popup_message").after('<div id="popup_panel"><input type="button" value="' + $.alerts.okButton +
												'" id="popup_ok" /> <input type="button" value="' + $.alerts.noButton +
												'" id="popup_no" /> <input type="button" value="' + $.alerts.cancelButton + '" id="popup_cancel" /></div>');
					$("#popup_ok").click( function() {
						$.alerts._hide();
						if( callback ) callback(true, false);
					});
					$("#popup_no").click( function() {
						$.alerts._hide();
						if( callback ) callback(false, false);
					});
					$("#popup_cancel").click( function() {
						$.alerts._hide();
						if( callback ) callback(false, true);
					});
					$("#popup_ok").focus();
					$("#popup_ok, #popup_cancel, #popup_no").keypress( function(e) {
						if( e.keyCode == 13 ) $("#popup_ok").trigger('click');
						if( e.keyCode == 27 ) $("#popup_cancel").trigger('click');
					});
				break;

				case 'Prompt':
					$("#popup_message").append('<br /><input type="text" size="30" id="popup_prompt" />').after('<div id="popup_panel"><input type="button" value="' + $.alerts.okButton + '" id="popup_ok" /> <input type="button" value="' + $.alerts.cancelButton + '" id="popup_cancel" /></div>');
					$("#popup_prompt").width( $("#popup_message").width() );
					$("#popup_ok").click( function() {
						var val = $("#popup_prompt").val();
						$.alerts._hide();
						if( callback ) callback( val );
					});
					$("#popup_cancel").click( function() {
						$.alerts._hide();
						if( callback ) callback( null );
					});
					$("#popup_prompt, #popup_ok, #popup_cancel").keypress( function(e) {
						if( e.keyCode == 13 ) $("#popup_ok").trigger('click');
						if( e.keyCode == 27 ) $("#popup_cancel").trigger('click');
					});
					if( value ) $("#popup_prompt").val(value);
					$("#popup_prompt").focus().select();
				break;
			}

			// Make draggable
			if( $.alerts.draggable ) {
				try {
					$("#popup_container").draggable({ handle: $("#popup_title") });
					$("#popup_title").css({ cursor: 'move' });
				} catch(e) { /* requires jQuery UI draggables */ }
			}
		},

		_hide: function() {
			$("#popup_container").remove();
			$.alerts._overlay('hide');
			$.alerts._maintainPosition(false);
		},

		_overlay: function(status) {
			switch( status ) {
				case 'show':
					$.alerts._overlay('hide');
					$("BODY").append('<div id="popup_overlay"></div>');
					$("#popup_overlay").css({
						position: 'absolute',
						zIndex: 99998,
						top: '0px',
						left: '0px',
						width: '100%',
						height: $(document).height(),
						background: $.alerts.overlayColor,
						opacity: $.alerts.overlayOpacity
					});
				break;
				case 'hide':
					$("#popup_overlay").remove();
				break;
			}
		},

		_reposition: function() {
			var top = (($(window).height() / 2) - ($("#popup_container").outerHeight() / 2)) + $.alerts.verticalOffset;
			var left = (($(window).width() / 2) - ($("#popup_container").outerWidth() / 2)) + $.alerts.horizontalOffset;
			if( top < 0 ) top = 0;
			if( left < 0 ) left = 0;

			// IE6 fix
			//if( $.browser.msie && parseInt($.browser.version) <= 6 ) top = top + $(window).scrollTop();

			$("#popup_container").css({
				top: top + 'px',
				left: left + 'px'
			});
			$("#popup_overlay").height( $(document).height() );
		},

		_maintainPosition: function(status) {
			if( $.alerts.repositionOnResize ) {
				switch(status) {
					case true:
						$(window).bind('resize', $.alerts._reposition);
					break;
					case false:
						$(window).unbind('resize', $.alerts._reposition);
					break;
				}
			}
		}

	}

	// Shortuct functions
	jAlert = function(message, title, callback) {
		$.alerts.okButton = '&nbsp;OK&nbsp;';
		$.alerts.alert(message, title, callback);
	}
	jError = function(message, title, callback) {
		$.alerts.okButton = '&nbsp;OK&nbsp;';
		$.alerts.alert_err(message, title, callback);
	}

	jConfirm = function(message, title, callback) {
		$.alerts.okButton = $.alerts.lang_ua ? '&nbsp;Так&nbsp;' : '&nbsp;Да&nbsp;';
		$.alerts.cancelButton = $.alerts.lang_ua ? '&nbsp;Ні&nbsp;' : '&nbsp;Нет&nbsp;';
		$.alerts.confirm(message, title, callback);
	};

	jConfirmAcc = function(message, title, callback) {
		$.alerts.okButton = $.alerts.lang_ua ? '&nbsp;Так&nbsp;' : '&nbsp;Да&nbsp;';
		$.alerts.cancelButton = $.alerts.lang_ua ? '&nbsp;Ні&nbsp;' : '&nbsp;Нет&nbsp;';
		$.alerts.confirm_acc(message, title, callback);
	};

	jYesNo = function(message, title, callback) {
		$.alerts.okButton = $.alerts.lang_ua ? '&nbsp;Так&nbsp;' : '&nbsp;Да&nbsp;';
		$.alerts.noButton = $.alerts.lang_ua ? '&nbsp;Ні&nbsp;' : '&nbsp;Нет&nbsp;';
		$.alerts.cancelButton = $.alerts.lang_ua ? '&nbsp;Відміна&nbsp;' : '&nbsp;Отмена&nbsp;';
		$.alerts.yesno(message, title, callback);
	};

	jPrompt = function(message, value, title, callback) {
		$.alerts.okButton = '&nbsp;OK&nbsp;';
		$.alerts.cancelButton = $.alerts.lang_ua ? '&nbsp;Відміна&nbsp;' : '&nbsp;Отмена&nbsp;';
		$.alerts.prompt(message, value, title, callback);
	};

})(jQuery);