<?php
	// Simple wrapper around WordPress admin-ajax, to enable
	// output buffering.
	if (!ob_get_level()) {
		ob_start();
	}

	// WordPress will automatically assume we are in the wp-admin directory.
	// Defining the following will let WP know this is not the case.
	define( 'WP_ADMIN', false );

	// Now, we include admin-ajax.php as if nothing happened...
	require dirname(						// WP base install dir
		dirname(							// wp-content
			dirname(						// plugins
				dirname(					// aceide
					dirname(				// src
						__FILE__
					)
				)
			)
		)
	) . '/wp-admin/admin-ajax.php';
