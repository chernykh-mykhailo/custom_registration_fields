<?php
/**
 * 2007-2026 PrestaShop
 */

class Custom_registration_fieldsGoogleloginModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $clientId = Configuration::get('GOOGLE_CLIENT_ID');
        $clientSecret = Configuration::get('GOOGLE_CLIENT_SECRET');
        $redirectUri = $this->context->link->getModuleLink('custom_registration_fields', 'googlelogin', [], true);

        if (Tools::getValue('code')) {
            $this->handleCallback($clientId, $clientSecret, $redirectUri);
        } else {
            $this->redirectToGoogle($clientId, $redirectUri);
        }
    }

    protected function redirectToGoogle($clientId, $redirectUri)
    {
        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'email profile',
            'access_type' => 'online',
        ]);

        Tools::redirect($url);
    }

    protected function handleCallback($clientId, $clientSecret, $redirectUri)
    {
        $code = Tools::getValue('code');

        // 1. Get Access Token
        $response = $this->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        $tokenData = json_decode($response, true);
        if (!isset($tokenData['access_token'])) {
            $this->errors[] = $this->module->l('Failed to get access token from Google.');
            return $this->redirectWithNotifications('login');
        }

        // 2. Get User Info
        $userInfo = json_decode(file_get_contents('https://www.googleapis.com/oauth2/v3/userinfo?access_token=' . $tokenData['access_token']), true);
        
        if (!isset($userInfo['email'])) {
            $this->errors[] = $this->module->l('Failed to get user info from Google.');
            return $this->redirectWithNotifications('login');
        }

        // 3. Find or Create Customer
        $customer = new Customer();
        $customer = $customer->getByEmail($userInfo['email']);

        if (!$customer || !$customer->id) {
            // Create new customer
            $customer = new Customer();
            $customer->email = $userInfo['email'];
            $customer->firstname = $userInfo['given_name'] ?? 'Google';
            $customer->lastname = $userInfo['family_name'] ?? 'User';
            $customer->passwd = Tools::encrypt(Tools::passwdGen());
            $customer->google_id = $userInfo['sub'];
            $customer->active = 1;
            $customer->add();
        } else {
            $customer->google_id = $userInfo['sub'];
            $customer->update();
        }

        // 4. Log in
        $this->context->updateCustomer($customer);

        // 5. Redirection Logic
        if (empty($customer->codice_fiscale)) {
            // Redirect to profile page to complete info
            Tools::redirect($this->context->link->getPageLink('identity', true) . '?complete_profile=1');
        } else {
            Tools::redirect('index.php');
        }
    }

    protected function post($url, $data)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}
