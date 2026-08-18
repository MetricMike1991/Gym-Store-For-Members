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
			array( 'Member', 'Email', 'Product', 'Cost (net) €', 'Price (VAT inc) €', 'VAT rate %', 'VAT owed €', 'Net after VAT €', 'Profit €', 'Requested', 'Status' )
		);

		$tot_cost   = 0.0;
		$tot_price  = 0.0;
		$tot_vat    = 0.0;
		$tot_net    = 0.0;
		$tot_profit = 0.0;

		foreach ( $rows as $r ) {
			$pr = GSFM_Products::pricing( $r );

			$tot_cost   += (float) $r->supplier_price;
			$tot_price  += $pr['effective'];
			$tot_vat    += $pr['vat_amount'];
			$tot_net    += $pr['net'];
			$tot_profit += $pr['profit'];

			fputcsv(
				$out,
				array(
					$r->display_name,
					$r->user_email,
					$r->title,
					number_format( (float) $r->supplier_price, 2, '.', '' ),
					number_format( $pr['effective'], 2, '.', '' ),
					number_format( $pr['vat_rate'], 2, '.', '' ),
					number_format( $pr['vat_amount'], 2, '.', '' ),
					number_format( $pr['net'], 2, '.', '' ),
					number_format( $pr['profit'], 2, '.', '' ),
					$r->requested_at,
					$r->status,
				)
			);
		}

		// Totals row.
		fputcsv(
			$out,
			array(
				'TOTALS',
				'',
				count( $rows ) . ' items',
				number_format( $tot_cost, 2, '.', '' ),
				number_format( $tot_price, 2, '.', '' ),
				'',
				number_format( $tot_vat, 2, '.', '' ),
				number_format( $tot_net, 2, '.', '' ),
				number_format( $tot_profit, 2, '.', '' ),
				'',
				'',
			)
		);

		fclose( $out );
		exit;
	}
}
