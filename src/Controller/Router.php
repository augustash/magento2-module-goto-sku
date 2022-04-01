<?php

/**
 * Product SKU Redirection Module
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2021 August Ash (https://www.augustash.com)
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
     * @var \Magento\Framework\App\ActionFactory
     */
    protected $actionFactory;

    /**
     * @var \Magento\Framework\App\Router\NoRouteHandler
     */
    protected $noRouteHandler;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \Magento\Framework\App\Response\Http
     */
    protected $response;

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
        ActionFactory $actionFactory,
        NoRouteHandler $noRouteHandler,
        ProductRepositoryInterface $productRepository,
        ResponseInterface $response
    ) {
        $this->actionFactory = $actionFactory;
        $this->noRouteHandler = $noRouteHandler;
        $this->productRepository = $productRepository;
        $this->response = $response;
    }

    /**
     * Match corresponding URL.
     *
     * @param RequestInterface $request
     * @return ActionInterface|null
     * @throws NoSuchEntityException
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
                $product = $this->productRepository->get(\urldecode($urlParts[1]));
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
}
