<?php
/**
 * The template for displaying all single posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package WordPress
 * @subpackage Twenty_Seventeen
 * @since 1.0
 * @version 1.0
 */
get_header(); ?>

<div class="wrap">
	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

			<?php
			/* Start the Loop */
			while ( have_posts() ) : the_post();?>

			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

				<header class="entry-header">
					<?php
					if ( is_single() ) {
						the_title( '<h1 class="entry-title">', '</h1>' );
					} else {
						the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' );
					}
					?>
				</header><!-- .entry-header -->

				<?php if ( '' !== get_the_post_thumbnail() && ! is_single() ) : ?>
					<div class="post-thumbnail">
						<a href="<?php the_permalink(); ?>">
							<?php the_post_thumbnail(); ?>
						</a>
					</div><!-- .post-thumbnail -->
				<?php endif; ?>

				<div class="entry-content">
					<?php
					/* translators: %s: Name of current post */
					the_content( sprintf(
						__( 'Continue reading<span class="screen-reader-text"> "%s"</span>', 'twentyseventeen' ),
						get_the_title()
					) );

					/** OSA metadata START */

					$post_id = get_the_ID();

					//contentFiles
					osa_render_attachments( $post_id, 'Załączniki' );

					//signature
					osa_render_meta( $post_id, 'osa_signature', 'Sygnatura' );

					//dates
					osa_render_date( $post_id, 'osa_dates', 'Data' );

					//sizeArchivalUnits
					osa_render_meta( $post_id, 'osa_sizeArchivalUnits', 'Rozmiar (jednostki archiwalne)' );

					//sizeMegabytes
					osa_render_meta( $post_id, 'osa_sizeMegabytes', 'Rozmiar (megabajty)' );

					//sizeRunnigMeters
					osa_render_meta( $post_id, 'osa_sizeRunningMeters', 'Rozmiar (metry bieżące)' );

					//authors
					osa_render_meta( $post_id, 'osa_authors', 'Twórca', 'name' );

					//languages
					osa_render_meta( $post_id, 'osa_languages', 'Język', 'name' );

					//content
					osa_render_meta( $post_id, 'osa_content', 'Zawartość' );

					//historicalArea
					osa_render_meta( $post_id, 'osa_historicalArea', 'Obszar historyczny' );

					//contemporaryArea
					osa_render_meta( $post_id, 'osa_contemporaryArea', 'Obszar współczesny' );

					//externalForm
					osa_render_meta( $post_id, 'osa_externalForm', 'Forma zewnętrzna' );

					//duplicates
					osa_render_meta( $post_id, 'osa_duplicates', 'Dublety' );

					//creationPlace
					osa_render_meta( $post_id, 'osa_creationPlace', 'Miejsce wytworzenia' );

					//internalForm
					osa_render_meta( $post_id, 'osa_internalForm', 'Forma wewnętrzna' );

					//availabilities
					osa_render_meta( $post_id, 'osa_availabilities', 'Dostępność', 'name' );

					//accessPremises
					osa_render_meta( $post_id, 'osa_accessPremises', 'Miejsce udostępnienia' );

					//accessTerms
					osa_render_meta( $post_id, 'osa_accessTerms', 'Warunki dostępu', 'name' );

					//licenses
					osa_render_meta( $post_id, 'osa_licenses', 'Licencje', 'name' );

					//link
					osa_render_link( $post_id, 'osa_link', 'Link' );

					//geographicalIndices
					osa_render_meta( $post_id, 'osa_geographicalIndices', 'Indeks geograficzny', 'name' );

					//materialIndices
					osa_render_meta( $post_id, 'osa_materialIndices', 'Indeks rzeczowy', 'name' );

					//personalIndices
					osa_render_meta( $post_id, 'osa_personalIndices', 'Indeks osobowy', 'name' );

					osa_render_children( $post_id, 'osa_unit', "Jednostki" );
					osa_render_children( $post_id, 'osa_series', "Serie" );
					osa_render_children( $post_id, 'osa_document', "Dokumenty" );

					/** OSA metadata END */

					wp_link_pages( array(
						'before'      => '<div class="page-links">' . __( 'Pages:', 'twentyseventeen' ),
						'after'       => '</div>',
						'link_before' => '<span class="page-number">',
						'link_after'  => '</span>',
					) );
					?>
				</div><!-- .entry-content -->

			</article><!-- #post-## -->

			<?php
			endwhile; // End of the loop.
			?>

		</main><!-- #main -->
	</div><!-- #primary -->
</div><!-- .wrap -->

<?php get_footer();
