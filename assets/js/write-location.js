/**
 * Sphotography — 撰写地点 meta box
 *
 * 读取浏览器定位，反向解析为行政区域并填充表单隐藏字段
 */
( function ( $ ) {
	'use strict';

	var cfg = window.SphotographyWloc || {};
	var i18n = cfg.i18n || {};

	$( function () {
		var $btn    = $( '#sphotography-wloc-locate' );
		var $status = $( '#sphotography-wloc-status' );
		var $lat    = $( '#sphotography-wloc-lat' );
		var $lng    = $( '#sphotography-wloc-lng' );
		var $region = $( '#sphotography-wloc-region' );
		var $label  = $( '#sphotography-wloc-region-label' );
		var $enable = $( '#sphotography-wloc-enabled' );

		if ( ! $btn.length ) {
			return;
		}

		$btn.on( 'click', function () {
			if ( ! navigator.geolocation ) {
				$status.text( i18n.unsupported || 'Geolocation unsupported.' );
				return;
			}
			$status.text( i18n.locating || '…' );
			$btn.prop( 'disabled', true );

			navigator.geolocation.getCurrentPosition(
				function ( pos ) {
					var lat = pos.coords.latitude;
					var lng = pos.coords.longitude;
					$lat.val( lat );
					$lng.val( lng );
					$status.text( i18n.resolving || '…' );

					$.post( cfg.ajaxUrl, {
						action: 'sphotography_wloc_resolve',
						nonce: cfg.nonce,
						lat: lat,
						lng: lng
					} ).done( function ( res ) {
						if ( res && res.success && res.data && res.data.region ) {
							$region.val( res.data.region );
							$label.text( res.data.region );
							$enable.prop( 'checked', true );
							$status.text( i18n.done || '' );
						} else {
							var msg = ( res && res.data && res.data.message ) ? res.data.message : ( i18n.unresolved || '' );
							$status.text( msg );
						}
						$btn.prop( 'disabled', false );
					} ).fail( function () {
						$status.text( i18n.unresolved || '' );
						$btn.prop( 'disabled', false );
					} );
				},
				function () {
					$status.text( i18n.denied || '' );
					$btn.prop( 'disabled', false );
				},
				{ enableHighAccuracy: false, timeout: 15000, maximumAge: 600000 }
			);
		} );
	} );
} )( jQuery );