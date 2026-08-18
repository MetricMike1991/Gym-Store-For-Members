<?php
/**
 * CSV export of member requests.
 *
 * @package GymStoreForMembers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSFM_Export {

	/**
	 * Stream requests as a CSV download and exit.
	 *
	 * @param string $status Optional status filter.
	 */
	public static function stream( $status = '' ) {
		$rows = GSFM_Wishlist::get_all( $status );

		$filename = 'member-requests-' . gmdate( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$out = fopen( 'php://output', 'w' );

		fputcsv(
			$out,
			array( 'Member', 'Email', 'Product', 'Supplier Price', 'Display Price', 'Requested', 'Status' )
		);

		foreach ( $rows as $r ) {
			fputcsv(
				$out,
				array(
					$r->display_name,
					$r->user_email,
					$r->title,
					number_format( (float) $r->supplier_price, 2 ),
					number_format( (float) $r->display_price, 2 ),
					$r->requested_at,
					$r->status,
				)
			);
		}

		fclose( $out );
		exit;
	}
}
