<?php defined( 'SYSPATH' ) or die( 'No direct script access.' );

/**
 * Analytics Controller
 *
 * PHP version 5
 * LICENSE: This source file is subject to LGPL license 
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/lesser.html
 * @author     Kundi Bora Team
 * @package    Ushahidi - http://source.ushahididev.com
 * @subpackage Analytics
 * @copyright  Ushahidi - http://www.ushahidi.com
 * @license    http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL) 
 */

class Analytics_json_Controller extends Controller {
	function __construct()
	{
			parent::__construct();
	}

	public function index()
	{
		return get();
	}

	public function get()
	{
		$json_features = $this->filter();
		$this->render_json( $json_features );
	}

	/**
	 * Render JSON object from php array
	 */
	protected function render_json( $json_features )
	{
		$json = json_encode( array(
			"type" => "ChartCollection",
			"chartData" => $json_features
		));

		header( 'Content-type: application/json; charset=utf-8');

		echo $json;
	}

	/**
	 * Filter charts
	 *
	 * Filters API:
	 * get? 
	 *  chartType = {'pie', 'line', 'bar'}
	 *  keyword = 'foo'
	 *  cumulative = {true, false}
	 *  countryId[] = [id1 id2 id3 ..]
	 *  categoryId[] = [id1 id2 id3 ..]
	 *  dateFrom = DATE
	 *  dateTo = DATE
	 */
	protected function filter(){
		$db = new Analytics_Model;

		// Parse query string
		parse_str( ltrim( Router::$query_string, "?" ), $filters );

		// Init filters
		$chart_type = null;
		$keyword = null;
		$cumulative = true;
		$date_from = null;
		$date_to = null;
		$category_id = null;
		$country_id = null;
		$chart_data = array();

		// Set chart type
		if( isset( $filters[ 'chartType' ] ) AND ! empty( $filters[ 'chartType' ] ) )
		{
			$chart_type = $filters[ 'chartType' ];
		}

		// Set keyword
		if( isset( $filters[ 'keyword' ] ) AND ! empty( $filters['keyword'] ) )
		{
			$keyword = $filters[ 'keyword' ];
		}

		// Set cumulative or daily 
		if( isset( $filters[ 'cumulative' ] ) AND ! empty( $filters['cumulative'] ) )
		{
			$cumulative = ( $filters['cumulative'] == 'true' ) ? true : false;
		}

		// Set country Id
		if( isset( $filters[ 'countryId' ] ) AND ! empty( $filters['countryId'] ) )
		{
			$country_id = $filters[ 'countryId' ];
		}

		// Set category Id
		if( isset( $filters[ 'categoryId' ] ) AND ! empty( $filters['categoryId'] ) )
		{
			$category_id = $filters[ 'categoryId' ];
		}

		// Set date from
		if( isset( $filters[ 'dateFrom' ] ) AND ! empty( $filters['dateFrom'] ) )
		{
			$date_from = date( "Y-m-d G:i:s", strtotime( $filters[ 'dateFrom' ] ) );
		}

		// Set date to
		if( isset( $filters[ 'dateTo' ] ) AND ! empty( $filters['dateTo'] ) )
		{
			$date_to = date( "Y-m-d G:i:s", strtotime( $filters[ 'dateTo' ] ) );
		}

		if( $chart_type == "pie" )
		{
			// query database
			$incidents = $db->get_incidents_by_id( $keyword, $category_id, $country_id, $date_from, $date_to );

			// create PHP object
			$series = array();
			foreach( $incidents as $incident )
			{
				$data = array(
					'label' => $incident->category_title,
					'data' => (int)$incident->incident_count
				);

				array_push($series, $data);
			}

			return $series;
		}
		else
		{
			// for each category create a data series
			$categories = $db->get_categories();
			foreach( $categories as $category )
			{
				// Skip categories that are not selected
				if( ! empty($category_id) AND ! in_array( $category->category_id, $category_id ) )
				{
					continue;
				}
				
				$incidents = $db->get_incidents( $keyword, $category->category_id, null,  false, $date_from, $date_to );

				// create data points
				$total = 0;
				$raw_data = array();
				foreach( $incidents as $incident )
				{
					// Skip countries that are not selected
					if( ! empty($country_id) AND ! in_array( $incident->country_id, $country_id ) )
					{
						continue;
					}

					$timestamp = strtotime( $incident->incident_date ) * 1000;  // convert to javascript timestamp
					$count = (int)$incident->incident_count;
					$total += $count;
					
					// Create data points
					$data = array(
						$timestamp,
						$cumulative ? $count : $total
					);

					array_push( $raw_data, $data );
				}

				// Create series labels
				$series = array(
					'label' => $category->category_title,
					'color' => $category->category_color,
					'data'  => $raw_data
				);

				array_push( $chart_data, $series );
			}
		}

		return $chart_data;
	}

	public function d3_para_coord_json()
	{
		$json_features = $this->create_d3_para_coord_json();
		$this->render_d3_json( $json_features );
	}
	
	public function render_d3_json( $json_features )
	{
		$json = json_encode( $json_features);

		header( 'Content-type: application/json; charset=utf-8');

		echo $json;
	}

	/**
	 * Create a JSON object
	 *
	 * @return a JSON object with the desired data to be rendered
	 */
	protected function create_d3_para_coord_json()
	{
		$db = new Analytics_Model;
		$search_time = 'Date (Unix)';
	
		// query database
		$query = $db->get_incidents_table_D3_pc();

		// create JSON object
		$json_features = array();
		foreach ( $query as $row )
		{
			$json_item = array();
			foreach ( $row as $key => $value )
			{

		
				if ( $key == $search_time )
				{
					$json_item[ $key ] =   strtotime( $value )  ;
		    //$json_item[ $key ] =  date('o-m', strtotime($value) ) ;
				}
				else
				{
					$json_item[ $key ] = $value;
				}
		 
		
		// $json_item[ $key ] = $value;
		 
			}
			array_push( $json_features, $json_item );
		}
		return $json_features;
	}

} // End Main
