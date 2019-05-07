<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Paypal\Controller\Transparent;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Session\Generic;
use Magento\Framework\Session\SessionManager;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Paypal\Model\Payflow\Service\Request\SecureToken;
use Magento\Paypal\Model\Payflow\Transparent;
use Magento\Quote\Model\Quote;

/**
 * Class RequestSecureToken
 *
 * @package Magento\Paypal\Controller\Transparent
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RequestSecureToken extends \Magento\Framework\App\Action\Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var Generic
     */
    private $sessionTransparent;

    /**
     * @var SecureToken
     */
    private $secureTokenService;

    /**
     * @var SessionManager|SessionManagerInterface
     */
    private $sessionManager;

    /**
     * @var Transparent
     */
    private $transparent;

    /**
     * @var Validator
     */
    private $formKeyValidator;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param Generic $sessionTransparent
     * @param SecureToken $secureTokenService
     * @param SessionManager $sessionManager
     * @param Transparent $transparent
     * @param SessionManagerInterface|null $sessionInterface
     * @param Validator $formKeyValidator
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Generic $sessionTransparent,
        SecureToken $secureTokenService,
        SessionManager $sessionManager,
        Transparent $transparent,
        SessionManagerInterface $sessionInterface = null,
        Validator $formKeyValidator = null
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->sessionTransparent = $sessionTransparent;
        $this->secureTokenService = $secureTokenService;
        $this->sessionManager = $sessionInterface ?: $sessionManager;
        $this->transparent = $transparent;
        $this->formKeyValidator = $formKeyValidator ?: ObjectManager::getInstance()->get(Validator::class);

        parent::__construct($context);
    }

    /**
     * Send request to PayfloPro gateway for get Secure Token
     *
     * @return ResultInterface
     */
    public function execute()
    {
        /** @var Quote $quote */
        $quote = $this->sessionManager->getQuote();

        if (!$quote || !$quote instanceof Quote || !$this->formKeyValidator->validate($this->getRequest())) {
            return $this->getErrorResponse();
        }

        $this->sessionTransparent->setQuoteId($quote->getId());
        try {
            $token = $this->secureTokenService->requestToken($quote);
            if (!$token->getData('securetoken')) {
                throw new \LogicException();
            }

            return $this->resultJsonFactory->create()->setData(
                [
                    $this->transparent->getCode() => ['fields' => $token->getData()],
                    'success' => true,
                    'error' => false
                ]
            );
        } catch (\Exception $e) {
            return $this->getErrorResponse();
        }
    }

    /**
     * Get error response.
     *
     * @return Json
     */
    private function getErrorResponse()
    {
        return $this->resultJsonFactory->create()->setData(
            [
                'success' => false,
                'error' => true,
                'error_messages' => __('Your payment has been declined. Please try again.')
            ]
        );
    }
}
