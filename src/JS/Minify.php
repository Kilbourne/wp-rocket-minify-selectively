<?php
namespace Kilbourne\WP_Rocket\Engine\Optimization\Minify\JS;

use WP_Rocket\Logger\Logger;
use WP_Rocket\Engine\Optimization\Minify\JS\Minify as WpRocketMinify;

class Minify extends WpRocketMinify{

	public function optimize( $html ) {
		Logger::info( 'JS MINIFICATION PROCESS STARTED.', [ 'js minification process' ] );

		$scripts = $this->get_scripts( $html );

		if ( empty( $scripts ) ) {
			return $html;
		}

		foreach ( $scripts as $script ) {
			global $wp_scripts;

			$is_external_url = $this->is_external_file( $script['url'] );

			if (
				$is_external_url
			) {
				continue;
			}

			if ( !$this->is_minify_included_file( $script ) ) {
				Logger::debug(
					'Script is excluded.',
					[
						'js minification process',
						'tag' => $script[0],
					]
				);
				continue;
			}

			if (
				preg_match( '/[-.]min\.js/iU', $script['url'] )
			) {
				Logger::debug(
					'Script is already minified.',
					[
						'js minification process',
						'tag' => $script[0],
					]
				);
				continue;
			}

			// Don't minify jQuery included in WP core since it's already minified but without .min in the filename.
			if ( ! empty( $wp_scripts->registered['jquery-core']->src ) && false !== strpos( $script['url'], $wp_scripts->registered['jquery-core']->src ) ) {
				Logger::debug(
					'jQuery script is already minified.',
					[
						'js minification process',
						'tag' => $script[0],
					]
				);
				continue;
			}

			$integrity_validated = $this->local_cache->validate_integrity( $script );

			if ( false === $integrity_validated ) {
				Logger::debug(
					'Script integrity attribute not valid.',
					[
						'js minification process',
						'tag' => $script[0],
					]
				);

				continue;
			}

			$script['final'] = $integrity_validated;

			$minify_url = $this->replace_url( strtok( $script['url'], '?' ) );

			if ( ! $minify_url ) {
				Logger::error(
					'Script minification failed.',
					[
						'js minification process',
						'tag' => $script[0],
					]
				);
				continue;
			}

			$html = $this->replace_script( $script, $minify_url, $html );
		}

		return $html;
	}

	protected function is_minify_included_file( array $tag ) {
		if ( ! isset( $tag[0], $tag['url'] ) ) {
			return false;
		}

		// File should not be minified.
		if (
			false !== strpos( $tag[0], 'data-minify=' )
			||
			false !== strpos( $tag[0], 'data-no-minify=' )
		) {
			return false;
		}

		$file_path = wp_parse_url( $tag['url'], PHP_URL_PATH );

		// File extension is not js.
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

		$included_files = (array) apply_filters( 'rocket_selectively_include_js', [] );

		if ( empty( $included_files ) ) {
			return '';
		}

		foreach ( $included_files as $i => $included_file ) {
			// Escape characters for future use in regex pattern.
			$included_files[ $i ] = str_replace( '#', '\#', $included_file );
		}

		return implode( '|', $included_files );
	}

	private function get_scripts( $html ) {
		$html_nocomments = $this->hide_comments( $html );
		$scripts         = $this->find( '<script\s+([^>]+[\s\'"])?src\s*=\s*[\'"]\s*?(?<url>[^\'"]+\.js(?:\?[^\'"]*)?)\s*?[\'"]([^>]+)?\/?>', $html_nocomments );

		if ( ! $scripts ) {
			Logger::debug( 'No `<script>` tags found.', [ 'js minification process' ] );
			return [];
		}

		Logger::debug(
			'Found ' . count( $scripts ) . ' <link> tags.',
			[
				'js minification process',
				'tags' => $scripts,
			]
		);

		return $scripts;
	}

	private function replace_script( $script, $minify_url, $html ) {
		$replace_script = str_replace( $script['url'], $minify_url, $script['final'] );
		$replace_script = str_replace( '<script', '<script data-minify="1"', $replace_script );
		$html           = str_replace( $script[0], $replace_script, $html );

		Logger::info(
			'Script minification succeeded.',
			[
				'js minification process',
				'url' => $minify_url,
			]
		);

		return $html;
	}
}
