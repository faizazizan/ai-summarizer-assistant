<?php
/**
 * Plugin Name: AI Summarizer Assistant – Universal
 * Description: Adds a sticky sidebar button to summarize articles via GPT or Gemini. Works in Gutenberg, Classic, Elementor, and Frontend.
 * Version: 1.0.0
 * Author: AI Assistant
 * Text Domain: ai-summarizer-assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Summarizer_Assistant {

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		// Admin/Editor assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_footer', array( $this, 'render_ui' ) );

		// Frontend assets
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_ui' ) );
	}

	public function enqueue_assets() {
		$this->load_scripts_styles();
	}

	public function enqueue_frontend_assets() {
		$show_on_frontend = apply_filters( 'aisummarizer_show_on_frontend', true );
		if ( ! $show_on_frontend ) {
			return;
		}
		$this->load_scripts_styles();
	}

	private function load_scripts_styles() {
		$plugin_url = plugin_dir_url( __FILE__ );
		$version    = '1.1.7'; // Bump version to bust cache

		wp_enqueue_style( 'aisummarizer-style', $plugin_url . 'assets/style.css', array(), $version );
		wp_enqueue_script( 'aisummarizer-app', $plugin_url . 'assets/app.js', array(), $version, true );

		global $post;
		$post_id = $post ? $post->ID : 0;

		// Try to get post ID from admin context if global $post is not set (e.g. some admin pages)
		if ( ! $post_id && isset( $_GET['post'] ) ) {
			$post_id = intval( $_GET['post'] );
		}

		wp_localize_script( 'aisummarizer-app', 'AISummarizer', array(
			'post_id'   => $post_id,
			'rest_url'  => esc_url_raw( rest_url( 'wp/v2/posts/' ) ),
			'rest_nonce'=> wp_create_nonce( 'wp_rest' ),
		) );
	}

	public function render_ui() {
		// Check frontend filter again if we are on frontend
		if ( ! is_admin() ) {
			$show_on_frontend = apply_filters( 'aisummarizer_show_on_frontend', true );
			if ( ! $show_on_frontend ) {
				return;
			}
		}

		?>
		<div id="aisummarizer-container">
			<button id="aisummarizer-toggle" aria-label="Open AI Summarizer" type="button">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="#FFD700" xmlns="http://www.w3.org/2000/svg">
					<path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" stroke="#FFD700" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
				<span>Let AI Summarize For You</span>
			</button>

			<div id="aisummarizer-modal" class="aisummarizer-hidden" role="dialog" aria-modal="true" aria-labelledby="aisummarizer-title">
				<div class="aisummarizer-backdrop"></div>
				<div class="aisummarizer-content">
					<button id="aisummarizer-close" aria-label="Close Modal" type="button">&times;</button>
					<h2 id="aisummarizer-title">Summarize Article</h2>
					<p>Click below to summarize the full content of this article with ChatGPT.</p>
					
					<!-- Credit Line -->
					<div class="aisummarizer-credit" style="text-align:center; font-size:11px; margin-bottom:10px; opacity:0.7;">
   					 Plugin built by <a href="https://faizazizan.com" target="_blank" style="text-decoration:underline;">Faiz Azizan</a>
					</div>

					<div class="aisummarizer-actions">
						<button class="aisummarizer-btn aisummarizer-gpt" data-provider="gpt">
							<!-- GPT Icon -->
							<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
								<path d="M22.2819 9.82116C22.1842 9.75734 22.0792 9.70429 21.9688 9.66289V9.65869C21.9688 9.65869 21.9288 9.64399 21.8288 9.60829C21.8225 9.60619 21.8162 9.60409 21.812 9.60199C21.5704 9.51379 21.3142 9.47599 21.0588 9.48829C20.8034 9.50059 20.5522 9.56269 20.3188 9.67099C19.9542 9.84063 19.6382 10.0934 19.3948 10.4099C19.1514 10.7264 18.9871 11.0983 18.9142 11.4967C18.8413 11.8951 18.8617 12.3094 18.9738 12.7072C19.0859 13.105 19.2867 13.4756 19.5604 13.7905L18.328 15.9262L17.0956 18.0619C16.8919 18.4149 16.5988 18.7081 16.2458 18.9118C15.8928 19.1155 15.4924 19.2224 15.0844 19.2224H12.6196H10.1548C9.7468 19.2224 9.3464 19.1155 8.9934 18.9118C8.6404 18.7081 8.3473 18.4149 8.1436 18.0619L9.376 15.9262L10.6084 13.7905C10.8821 13.4756 11.0829 13.105 11.195 12.7072C11.3071 12.3094 11.3275 11.8951 11.2546 11.4967C11.1817 11.0983 11.0174 10.7264 10.774 10.4099C10.5306 10.0934 10.2146 9.84063 9.85 9.67099C9.6166 9.56269 9.3654 9.50059 9.11 9.48829C8.8546 9.47599 8.5984 9.51379 8.3568 9.60199C8.3526 9.60409 8.3463 9.60619 8.34 9.60829C8.24 9.64399 8.2 9.65869 8.2 9.65869V9.66289C8.0896 9.70429 7.9846 9.75734 7.8869 9.82116C7.5187 10.0624 7.2255 10.3954 7.0366 10.7866C6.8477 11.1778 6.7696 11.6134 6.8101 12.0496C6.8506 12.4858 7.0084 12.907 7.2674 13.2706C7.5264 13.6342 7.8778 13.9274 8.2864 14.1208L9.5188 16.2565L10.7512 18.3922C10.9549 18.7452 11.248 19.0384 11.601 19.2421C11.954 19.4458 12.3544 19.5527 12.7624 19.5527H15.2272H17.692C18.1 19.5527 18.5004 19.4458 18.8534 19.2421C19.2064 19.0384 19.4995 18.7452 19.7032 18.3922L20.9356 16.2565L22.168 14.1208C22.5766 13.9274 22.928 13.6342 23.187 13.2706C23.446 12.907 23.6038 12.4858 23.6443 12.0496C23.6848 11.6134 23.6067 11.1778 23.4178 10.7866C23.2289 10.3954 22.9357 10.0624 22.5675 9.82116H22.2819Z" fill="currentColor"/>
								<path d="M2.5324 12.7072C2.6445 13.105 2.8453 13.4756 3.119 13.7905L4.3514 15.9262L5.5838 18.0619C5.7875 18.4149 6.0806 18.7081 6.4336 18.9118C6.7866 19.1155 7.187 19.2224 7.595 19.2224H10.0598H12.5246C12.9326 19.2224 13.333 19.1155 13.686 18.9118C14.039 18.7081 14.3321 18.4149 14.5358 18.0619L13.3034 15.9262L12.071 13.7905C11.7973 13.4756 11.5965 13.105 11.4844 12.7072C11.3723 12.3094 11.3519 11.8951 11.4248 11.4967C11.4977 11.0983 11.662 10.7264 11.9054 10.4099C12.1488 10.0934 12.4648 9.84063 12.8294 9.67099C13.0628 9.56269 13.314 9.50059 13.5694 9.48829C13.8248 9.47599 14.081 9.51379 14.3226 9.60199C14.3268 9.60409 14.3331 9.60619 14.3394 9.60829C14.4394 9.64399 14.4794 9.65869 14.4794 9.65869V9.66289C14.5898 9.70429 14.6948 9.75734 14.7925 9.82116C15.1607 10.0624 15.4539 10.3954 15.6428 10.7866C15.8317 11.1778 15.9098 11.6134 15.8693 12.0496C15.8288 12.4858 15.671 12.907 15.412 13.2706C15.153 13.6342 14.8016 13.9274 14.393 14.1208L13.1606 16.2565L11.9282 18.3922C11.7245 18.7452 11.4314 19.0384 11.0784 19.2421C10.7254 19.4458 10.325 19.5527 9.917 19.5527H7.4522H4.9874C4.5794 19.5527 4.179 19.4458 3.826 19.2421C3.473 19.0384 3.1799 18.7452 2.9762 18.3922L1.7438 16.2565L0.5114 14.1208C0.1028 13.9274 -0.2486 13.6342 -0.5076 13.2706C-0.7666 12.907 -0.9244 12.4858 -0.9649 12.0496C-1.0054 11.6134 -0.9273 11.1778 -0.7384 10.7866C-0.5495 10.3954 -0.2563 10.0624 0.1119 9.82116H0.3975C0.4952 9.75734 0.6002 9.70429 0.7106 9.66289V9.65869C0.7106 9.65869 0.7506 9.64399 0.8506 9.60829C0.8569 9.60619 0.8632 9.60409 0.8674 9.60199C1.109 9.51379 1.3652 9.47599 1.6206 9.48829C1.876 9.50059 2.1272 9.56269 2.3606 9.67099C2.7252 9.84063 3.0412 10.0934 3.2846 10.4099C3.528 10.7264 3.6923 11.0983 3.7652 11.4967C3.8381 11.8951 3.8177 12.3094 3.7056 12.7072H2.5324Z" fill="currentColor"/>
							</svg>
							GPT Summarizer
						</button>
					</div>
					<div class="aisummarizer-notice">
						<small>Note: Opens in a new tab. Content is sent via URL parameter. Just press enter to start.</small>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}

new AI_Summarizer_Assistant();
