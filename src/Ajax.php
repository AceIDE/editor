<?php
	// Simple wrapper around WordPress admin-ajax, to enable
	// output buffering.
	if (!ob_get_level()) {
		ob_start();
	}

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
