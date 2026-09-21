( function () {
	const cfg = window.MPesaPoll;

	if ( ! cfg ) {
		return;
	}

	const statusEl = document.getElementById( cfg.statusId );
	const maxAttempts = Math.ceil( cfg.timeout / cfg.interval );
	let attempts = 0;

	const render = ( html ) => {
		if ( statusEl ) {
			statusEl.innerHTML = html;
		}
	};

	const stopAndReport = ( message ) => {
		render(
			message +
			' <a href="' + cfg.retryUrl + '">' +
			window.MPesaPoll.retryLabel +
			'</a>'
		);
	};

	const check = async () => {
		attempts++;

		try {
			const res = await fetch( cfg.statusUrl, { credentials: 'same-origin' } );
			const data = await res.json();

			if ( 'paid' === data.status ) {
				window.location.assign( cfg.successUrl );

				return;
			}

			if ( 'failed' === data.status ) {
				render( window.MPesaPoll.failedLabel );

				return;
			}
		} catch ( e ) {
			if ( attempts >= maxAttempts ) {
				stopAndReport( window.MPesaPoll.timedOutLabel );

				return;
			}
		}

		if ( attempts >= maxAttempts ) {
			stopAndReport( window.MPesaPoll.timedOutLabel );

			return;
		}

		setTimeout( check, cfg.interval );
	};

	setTimeout( check, cfg.interval );
} )();