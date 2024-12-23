<?php
namespace Kilbourne\WP_Rocket\Engine\Optimization\Minify\JS;
use WP_Rocket\Engine\Optimization\AssetsLocalCache;
use WP_Rocket\Engine\Optimization\Minify\JS\Subscriber as WpRocketSubscriber;
/**
 * Minify/Combine JS subscriber
 *
 * @since 3.1
 */
class Subscriber extends WpRocketSubscriber {

	public function process( $html ) {
		if ( ! $this->is_allowed() ) {
			return $html;
		}

		$assets_local_cache = new AssetsLocalCache( rocket_get_constant( 'WP_ROCKET_MINIFY_CACHE_PATH' ), $this->filesystem );
		$container          = apply_filters( 'rocket_container', null );
		$dynamic_lists      = $container->get( 'dynamic_lists' );

		$this->set_processor_type( new Minify( $this->options, $assets_local_cache,$dynamic_lists ) );

		return $this->processor->optimize( $html );
	}

	protected function is_allowed() {
		if ( rocket_get_constant( 'DONOTROCKETOPTIMIZE' ) ) {
			return false;
		}

		return true;
	}
}
