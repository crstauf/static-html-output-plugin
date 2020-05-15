<?php

class Netlify extends SitePublisher {

    public function __construct() {
        $this->loadSettings( 'netlify' );

        $this->settings['netlifySiteID'];
        $this->settings['netlifyPersonalAccessToken'];
        $this->base_url = 'https://api.netlify.com';

        $this->detectSiteID();

        if ( defined( 'WP_CLI' ) ) {
            return; }

        switch ( $_POST['ajax_action'] ) {
            case 'test_netlify':
                $this->loadArchive();
                $this->test_netlify();
                break;
            case 'netlify_do_export':
                $this->bootstrap();
                $this->loadArchive();
                $this->deploy();
                break;
        }
    }

    public function detectSiteID() {
        $this->site_id = $this->settings['netlifySiteID'];

        if ( strpos( $this->site_id, 'netlify.com' ) !== false ) {
            return;
        } elseif ( strpos( $this->site_id, '.' ) !== false ) {
            return;
        } elseif ( strlen( $this->site_id ) === 37 ) {
            return;
        } else {
            $this->site_id .= '.netlify.com';
        }
    }

    public function deploy() {
        $this->zip_archive_path = $this->settings['wp_uploads_path'] . '/' .
            $this->archive->name . '.zip';

        $zip_deploy_endpoint = $this->base_url . '/api/v1/sites/' .
            $this->site_id . '/deploys';

        try {
            $headers = [
                'Authorization: Bearer ' .
                    $this->settings['netlifyPersonalAccessToken'],
                'Content-Type: application/zip',
            ];

            $this->client = new StaticHTMLOutput_Request();

            $this->client->postWithFileStreamAndHeaders(
                $zip_deploy_endpoint,
                $this->zip_archive_path,
                $headers
            );

            $this->checkForValidResponses(
                $this->client->status_code,
                [ '200', '201', '301', '302', '304' ]
            );

            $this->finalizeDeployment();
        } catch ( Exception $e ) {
            $this->handleException( $e );
        }
    }

    public function test_netlify() {
        $this->zip_archive_path = $this->settings['wp_uploads_path'] . '/' .
            $this->archive->name . '.zip';

        $site_info_endpoint = $this->base_url . '/api/v1/sites/' .
            $this->site_id;

        try {

            $headers = [
                'Authorization: Bearer ' .
                    $this->settings['netlifyPersonalAccessToken'],
            ];

            $this->client = new StaticHTMLOutput_Request();

            $this->client->getWithCustomHeaders(
                $site_info_endpoint,
                $headers
            );

            // NOTE: check for certain header, as response is always 200
            if ( isset( $this->client->headers['x-ratelimit-limit'] ) ) {
                if ( ! defined( 'WP_CLI' ) ) {
                    echo 'SUCCESS';
                }
            } else {
                $code = 404;

                WsLog::l(
                    'BAD RESPONSE STATUS FROM API (' . $code . ')'
                );

                http_response_code( $code );

                echo 'Netlify test error';
            }
        } catch ( Exception $e ) {
            $this->handleException( $e );
        }
    }
}

