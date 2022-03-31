<?php
namespace Kilbourne\WP_Rocket\Engine\Optimization\Minify\CSS;

use WP_Rocket\Engine\Optimization\Minify\CSS\Subscriber as WpRocketSubscriber;
use WP_Rocket\Engine\Optimization\AssetsLocalCache;

/**
 * Minify/Combine CSS subscriber
 *
 * @since 3.1
 */
class Subscriber extends WpRocketSubscriber {

	public function process( $html ) {
		if ( ! $this->is_allowed() ) {
			return $html;
		}

		$assets_local_cache = new AssetsLocalCache( rocket_get_constant( 'WP_ROCKET_MINIFY_CACHE_PATH' ), $this->filesystem );

		$this->set_processor_type( new Minify( $this->options, $assets_local_cache ) );

		return $this->processor->optimize( $html );
	}

	protected function is_allowed() {
		if ( rocket_get_constant( 'DONOTROCKETOPTIMIZE' ) ) {
			return false;
		}

		return true;
	}
}
