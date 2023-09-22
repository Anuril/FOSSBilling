<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Client\Controller;

use Error;

class Guest implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function register(\Box_App &$app)
    {
        $app->get('/guest/reset-password-confirm/:hash', 'get_reset_password_confirm', ['hash' => '[a-z0-9]+'], static::class);
    }

    public function get_reset_password_confirm(\Box_App $app, $hash)
    {
        $api = $this->di['api_client'];
        $this->di['events_manager']->fire(['event' => 'onBeforePasswordResetClient']);
        $data = [
            'hash' => $hash,
        ];
        $template = 'mod_client_set_new_password';
        
        // Chech if the hash is valid
        // Call confirm_reset_calid API and if true, then render the template, otherwise redirect to login page
        $result = $api->guest_pwreset_valid($data);
        error_log("Check Hash Result: " . $result);
        if ($result) {
            return $app->render($template);
        } else {
            $app->redirect('/client/login');
        }
    }
}
