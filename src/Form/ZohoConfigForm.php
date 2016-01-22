<?php

/**
 * @file
 * Contains Drupal\zoho\Form\ZohoConfigForm.
 */

namespace Drupal\zoho\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ZohoConfigForm.
 *
 * @package Drupal\zoho\Form
 */
class ZohoConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'zoho.zohoconfig',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'zoho_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('zoho.zohoconfig');
    $form['zoho_api_authtoken'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Zoho API Authtoken'),
      '#description' => $this->t('Enter a Valid API Authtoken. To use an existing Authtoken, login to zoho crm and goto <a href="https://accounts.zoho.com/u/h#setting/authtoken" target="_blank">Existing tokens</a><br>OR<br>Leave this blank and enter your zoho crm username and password in the below fieldset to generate a new Authtoken.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('zoho_api_authtoken'),
    );
    $form['generate_new_authtoken'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Generate New Authtoken'),
      '#description' => $this->t('Use this only if you need to generate a new Authtoken. And to generate a new token, the above Authtoken field should be empty.<br><strong>Username and password are NOT stored anywhere.</strong>'),
    );
    $form['generate_new_authtoken']['zoho_username_email'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Zoho Username/Email'),
      '#maxlength' => 64,
      '#size' => 64,
      '#required' => TRUE,
      '#default_value' => $config->get('zoho_username_email'),
    );
    $form['generate_new_authtoken']['zoho_password'] = array(
      '#type' => 'password',
      '#title' => $this->t('Zoho Password'),
      '#maxlength' => 64,
      '#size' => 64,
      '#required' => TRUE,
      '#default_value' => $config->get('zoho_password'),
    );
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $result = $this->zoho_generate_authtoken($form_state->getValue('zoho_username_email'), $form_state->getValue('zoho_password'));
    if (isset($result['authtoken'])) {
      drupal_set_message(t('Successfully generated a new Authtoken : :token', array(':token' => $result['authtoken'])));
      $form_state->setValue('zoho_api_authtoken', $result['authtoken']);
    }
    else {
      form_set_error(t('There was an error: :error.<br/>Please resolve the error and try again.', array(':error' => $result['error'])), 'error');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('zoho.zohoconfig')
        ->set('zoho_api_authtoken', $form_state->getValue('zoho_api_authtoken'))
        ->set('generate_new_authtoken', $form_state->getValue('generate_new_authtoken'))
        ->set('zoho_username_email', $form_state->getValue('zoho_username_email'))
        ->set('zoho_password', $form_state->getValue('zoho_password'))
        ->save();
  }

  /**
   * Generates a new ZOHO Auth Token and stores in db.
   *
   * @param string $username
   *   User name of the Zoho crm account
   * @param string $pass
   *   Password for Zoho crm account
   *
   * @returns array
   *   Returns an array with authtoken if success and error otherwise.
   */
  function zoho_generate_authtoken($username, $pass) {
    $url = ZOHO_TICKET_URL . "apiauthtoken/nb/create?SCOPE=ZohoCRM/crmapi&EMAIL_ID=" . $username . "&PASSWORD=" . $pass;
    try {
      $response = \Drupal::httpClient()->get($url, array('headers' => array('Accept' => 'text/plain')));
      $result = (string) $response->getBody();
      $anarray = explode("\n", $result);
      $authtoken = explode("=", $anarray['2']);
      $cmp = strcmp($authtoken['0'], "AUTHTOKEN");
      if ($cmp == 0) {
        return array('authtoken' => $authtoken['1']);
      }
      else {
        return array('error' => $authtoken['1']);
      }
    }
    catch (RequestException $ex) {
      watchdog_exception('zoho', $ex);
      return array('error' => $ex);
    }
  }

}
