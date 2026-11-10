/**
 * One TinyMCE editor shared by all emails. The dropdown picks which email is shown; the
 * values of all emails live in hidden fields that are submitted with the settings form.
 */
( function () {
	'use strict';

	var EDITOR_ID = 'chp-mail-body';
	var select = document.getElementById( 'chp-mail-select' );
	var subject = document.getElementById( 'chp-mail-subject' );
	var reset = document.getElementById( 'chp-mail-reset' );
	var hint = document.getElementById( 'chp-mail-placeholders' );
	var textarea = document.getElementById( EDITOR_ID );
	if ( ! select || ! subject || ! textarea ) {
		return;
	}

	var defaults = window.chpMailDefaults || {};
	var current = select.value;

	function field( attr, key ) {
		return document.querySelector( '[data-chp-' + attr + '="' + key + '"]' );
	}

	function getBody() {
		var editor = window.tinymce && window.tinymce.get( EDITOR_ID );
		return editor && ! editor.isHidden() ? editor.getContent() : textarea.value;
	}

	function setBody( html ) {
		var editor = window.tinymce && window.tinymce.get( EDITOR_ID );
		if ( editor && ! editor.isHidden() ) {
			editor.setContent( html );
		} else {
			textarea.value = html;
		}
	}

	function store() {
		field( 'subject', current ).value = subject.value;
		field( 'body', current ).value = getBody();
	}

	function show( key ) {
		current = key;
		subject.value = field( 'subject', key ).value;
		subject.placeholder = defaults[ key ] ? defaults[ key ].subject : '';
		setBody( field( 'body', key ).value );
		hint.textContent = select.options[ select.selectedIndex ].getAttribute( 'data-placeholders' );
	}

	select.addEventListener( 'change', function () {
		store();
		show( select.value );
	} );

	reset.addEventListener( 'click', function () {
		if ( defaults[ current ] ) {
			subject.value = '';
			setBody( defaults[ current ].body );
		}
	} );

	subject.form.addEventListener( 'submit', store );

	// The subject and placeholder hint depend on the editor only for the body; fill them right away.
	subject.value = field( 'subject', current ).value;
	subject.placeholder = defaults[ current ] ? defaults[ current ].subject : '';
	hint.textContent = select.options[ select.selectedIndex ].getAttribute( 'data-placeholders' );
}() );
