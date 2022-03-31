<?php
namespace Kilbourne\WP_Rocket\Engine\Optimization\Minify\CSS;

use WP_Rocket\Engine\Optimization\Minify\CSS\Minify as WpRocketMinify;
use WP_Rocket\Logger\Logger;

class Minify extends WpRocketMinify {

	public function optimize( $html ) {
		Logger::info( 'CSS MINIFICATION PROCESS STARTED.', [ 'css minification process' ] );

		$styles = $this->get_styles( $html );

		if ( empty( $styles ) ) {
			return $html;
		}

		foreach ( $styles as $style ) {
			if ( !$this->is_minify_included_file( $style ) ) {
				Logger::debug(
					'Style is excluded.',
					[
						'css minification process',
						'tag' => $style[0],
					]
				);

				continue;
			}

			$integrity_validated = $this->local_cache->validate_integrity( $style );

			if ( false === $integrity_validated ) {
				Logger::debug(
					'Style integrity attribute not valid.',
					[
						'css minification process',
						'tag' => $style[0],
					]
				);

				continue;
			}

			$style['final'] = $integrity_validated;

			$minify_url = $this->replace_url( strtok( $style['url'], '?' ) );

			if ( ! $minify_url ) {
				Logger::error(
					'Style minification failed.',
					[
						'css minification process',
						'tag' => $style[0],
					]
				);
				continue;
			}

			$html = $this->replace_style( $style, $minify_url, $html );
		}

		return $html;
	}

	protected function is_minify_included_file( array $tag ) {
		if ( ! isset( $tag[0], $tag['url'] ) ) {
			return false;
		}

		// File should not be minified.
		if ( false !== strpos( $tag[0], 'data-minify=' ) || false !== strpos( $tag[0], 'data-no-minify=' ) ) {
			return false;
		}

		if ( false !== strpos( $tag[0], 'media=' ) && ! preg_match( '/media=["\'](?:\s*|[^"\']*?\b(all|screen)\b[^"\']*?)["\']/i', $tag[0] ) ) {
			return false;
		}

		$file      = wp_parse_url( $tag['url'] );
		$file_path = isset( $file['path'] ) ? $file['path'] : null;
		$host      = isset( $file['host'] ) ? $file['host'] : null;

		// File extension is not css.
		if ( pathinfo( $file_path, PATHINFO_EXTENSION ) !== self::FILE_TYPE ) {
			return false;
		}

		$included_files = $this->get_included_files();

		if ( ! empty( $included_files ) ) {
			// File is included from minification/concatenation.
			if ( preg_match( '#(' . $included_files . ')#', $file_path ) ) {
				return true;
			}
		}

		return false;
	}

	protected function get_included_files() {

		$included_files = (array) apply_filters( 'rocket_selectively_include_css', [] );

		if ( empty( $included_files ) ) {
			return '';
		}

		foreach ( $included_files as $i => $included_file ) {
			$included_files[ $i ] = str_replace( '#', '\#', $included_file );
		}

		return implode( '|', $included_files );
	}

	private function replace_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		// This filter is documented in /inc/classes/optimization/class-abstract-optimization.php.
		$url = apply_filters( 'rocket_asset_url', $url, $this->get_zones() );

		$parsed_url = wp_parse_url( $url );

		if ( empty( $parsed_url['path'] ) ) {
			return false;
		}

		if ( ! empty( $parsed_url['host'] ) ) {
			$url = rocket_add_url_protocol( $url );
		}

		$filename      = ltrim( rocket_realpath( $parsed_url['path'] ), '/' );
		$minified_file = rawurldecode( $this->minify_base_path . $filename );

		if ( rocket_direct_filesystem()->exists( $minified_file ) ) {
			Logger::debug(
				'Minified CSS file already exists.',
				[
					'css minification process',
					'path' => $minified_file,
				]
			);

			return $this->get_full_minified_url( $minified_file, $this->get_minify_url( $filename, $url ) );
		}

		$external_url = $this->is_external_file( $url );
		$file_path    = $external_url ? $this->local_cache->get_filepath( $url ) : $this->get_file_path( $url );

		if ( empty( $file_path ) ) {
			Logger::error(
				'Couldn’t get the file path from the URL.',
				[
					'css minification process',
					'url' => $url,
				]
			);

			return false;
		}

		$file_content = $external_url ? $this->local_cache->get_content( $url ) : $this->get_file_content( $file_path );

		if ( ! $file_content ) {
			Logger::error(
				'No file content.',
				[
					'css minification process',
					'path' => $file_path,
				]
			);

			return false;
		}

		$minified_content = $external_url ? $this->minify( $url, $minified_file, $file_content ) : $this->minify( $file_path, $minified_file, $file_content );

		if ( empty( $minified_content ) ) {
			return false;
		}

		$minified_content = $this->font_display_swap( $url, $minified_file, $minified_content );

		if ( empty( $minified_content ) ) {
			return false;
		}

		$save_minify_file = $this->save_minify_file( $minified_file, $minified_content );

		if ( ! $save_minify_file ) {
			return false;
		}

		return $this->get_full_minified_url( $minified_file, $this->get_minify_url( $filename, $url ) );
	}
}
