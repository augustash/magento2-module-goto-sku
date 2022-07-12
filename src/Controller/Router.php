<?php

/**
 * Product SKU Redirection Module
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright 2022 August Ash, Inc. (https://www.augustash.com)
 */

declare(strict_types=1);

namespace Augustash\GotoSku\Controller;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\Action\Redirect;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\Router\NoRouteHandler;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;

/**
 * Match Goto SKU URL class.
 */
class Router implements RouterInterface
{
    /**
     * Constructor.
     *
     * Initialize class dependencies.
     *
     * @param \Magento\Framework\App\ActionFactory $actionFactory
     * @param \Magento\Framework\App\Router\NoRouteHandler $noRouteHandler
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Framework\App\ResponseInterface $response
     */
    public function __construct(
        protected ActionFactory $actionFactory,
        protected NoRouteHandler $noRouteHandler,
        protected ProductRepositoryInterface $productRepository,
        protected ResponseInterface $response
    ) {
    }

    /**
     * Match corresponding URL.
     *
     * @param \Magento\Framework\App\RequestInterface $request
     * @return \Magento\Framework\App\ActionInterface|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function match(RequestInterface $request): ?ActionInterface
    {
        /** @var \Magento\Framework\App\Request\Http $request */
        $identifier = \trim($request->getPathInfo(), '/');
        $urlParts = \explode('/', $identifier);

        // check for a match and also that a forward hasn't already been set
        if (empty($request->getBeforeForwardInfo())
            && \count($urlParts) == 2 && $urlParts[0] == 'goto') {
            try {
                /** @var \Magento\Catalog\Model\Product $product */
                $product = $this->productRepository->get($this->getProductIdentifier($urlParts));
                $redirectUrl = $product->getUrlModel()->getUrl($product);
                $this->response->setRedirect($redirectUrl, 301);
                $request->setDispatched(true);
                // stop processing matches and redirect
                return $this->actionFactory->create(Redirect::class);
            } catch (NoSuchEntityException $e) {
                // stop processing and allow others to handle the request
                $request->initForward();
                $request->setAlias(UrlInterface::REWRITE_REQUEST_PATH_ALIAS, $identifier);
                $this->noRouteHandler->process($request);
                return $this->actionFactory->create(Forward::class);
            }
        }

        return null;
    }

    /**
     * Examine URL parts and pull out the product identifier.
     * 
     * Used for other modules to "plugin".
     *
     * @param array $urlParts
     * @return string
     */
    public function getProductIdentifier(array $urlParts): string
    {
        return \urldecode($urlParts[1]);
    }
}
