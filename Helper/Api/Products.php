<?php

namespace StoreKeeper\StoreKeeper\Helper\Api;

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\CategoryLinkRepositoryInterface;
use Magento\Catalog\Api\Data\ProductLinkInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Controller\Adminhtml\Product\Initialization\Helper\AttributeFilter;
use Magento\CatalogInventory\Model\Stock\Item;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;
use Magento\Store\Model\StoreManagerInterface;
use Parsedown;
use Psr\Log\LoggerInterface;

/**
 * @depracated
 */
class Products extends \Magento\Framework\App\Helper\AbstractHelper
{
    const API_URL = 'https://api-creativectdev.storekeepercloud.com';
    private $websiteIds = [];
    private Auth $authHelper;
    private ProductFactory $productFactory;
    private ProductRepositoryInterface $productRepository;
    private CollectionFactory $productCollectionFactory;
    private CategoryCollectionFactory $categoryCollectionFactory;
    private CategoryLinkManagementInterface $categoryLinkManagement;
    private CategoryLinkRepositoryInterface $categoryLinkRepository;
    private CategoryRepository $categoryRepository;
    private StoreManagerInterface $storeManager;
    private Item $stockItem;
    private AttributeFilter $attributeFilter;
    private DirectoryList $directoryList;
    private File $file;
    private SourceItemsSaveInterface $sourceItemsSave;
    private SourceItemInterfaceFactory $sourceItemFactory;
    private ProductLinkInterfaceFactory $productLinkFactory;
    private StockRegistryInterface $stockRegistry;
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param Auth $authHelper
     * @param ProductFactory $productFactory
     * @param ProductRepositoryInterface $productRepository
     * @param CollectionFactory $productCollectionFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param CategoryLinkManagementInterface $categoryLinkManagement
     * @param CategoryLinkRepositoryInterface $categoryLinkRepository
     * @param CategoryRepository $categoryRepository
     * @param StoreManagerInterface $storeManager
     * @param Item $stockItem
     * @param AttributeFilter $attributeFilter
     * @param DirectoryList $directoryList
     * @param File $file
     * @param SourceItemsSaveInterface $sourceItemsSave
     * @param SourceItemInterfaceFactory $sourceItemFactory
     * @param ProductLinkInterfaceFactory $productLinkFactory
     * @param StockRegistryInterface $stockRegistry
     * @param LoggerInterface $logger
     */
    public function __construct(
        Auth $authHelper,
        ProductFactory $productFactory,
        ProductRepositoryInterface $productRepository,
        CollectionFactory $productCollectionFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        CategoryLinkManagementInterface $categoryLinkManagement,
        CategoryLinkRepositoryInterface $categoryLinkRepository,
        CategoryRepository $categoryRepository,
        StoreManagerInterface $storeManager,
        Item $stockItem,
        AttributeFilter $attributeFilter,
        DirectoryList $directoryList,
        File $file,
        SourceItemsSaveInterface $sourceItemsSave,
        SourceItemInterfaceFactory $sourceItemFactory,
        ProductLinkInterfaceFactory $productLinkFactory,
        StockRegistryInterface $stockRegistry,
        LoggerInterface $logger
    ) {
        $this->authHelper = $authHelper;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->categoryLinkManagement = $categoryLinkManagement;
        $this->categoryLinkRepository = $categoryLinkRepository;
        $this->storeManager = $storeManager;
        $this->stockItem = $stockItem;
        $this->attributeFilter = $attributeFilter;
        $this->directoryList = $directoryList;
        $this->file = $file;
        $this->sourceItemsSave = $sourceItemsSave;
        $this->sourceItemFactory = $sourceItemFactory;
        $this->productLinkFactory = $productLinkFactory;
        $this->stockRegistry = $stockRegistry;
        $this->logger = $logger;
    }

    /**
     * Auth check
     *
     * @param $storeId
     * @return string|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function authCheck($storeId)
    {
        return $this->authHelper->authCheck($storeId);
    }

    /**
     * Get language for store
     *
     * @param $storeId
     * @return mixed|string
     */
    public function getLanguageForStore($storeId)
    {
        return $this->authHelper->getLanguageForStore($storeId);
    }

    /**
     * Natural search shop flat products
     *
     * @param $storeId
     * @param string $query
     * @param string $lang
     * @param int $start
     * @param int $limit
     * @param array $order
     * @param array $filters
     * @return mixed
     * @throws \Exception
     */
    public function naturalSearchShopFlatProducts(
        $storeId,
        string $query,
        string $lang,
        int $start,
        int $limit,
        array $order,
        array $filters
    ) {
        return $this->authHelper->getModule('ShopModule', $storeId)->naturalSearchShopFlatProducts(
            $query,
            $lang,
            $start,
            $limit,
            $order,
            $filters
        );
    }

    /**
     * Natural search shop flat products for hooks
     *
     * @param $storeId
     * @param string $query
     * @param string $lang
     * @param int $start
     * @param int $limit
     * @param array $order
     * @param array $filters
     * @return mixed
     * @throws \Exception
     */
    public function naturalSearchShopFlatProductForHooks(
        $storeId,
        string $query,
        string $lang,
        int $start,
        int $limit,
        array $order,
        array $filters
    ) {
        return $this->authHelper->getModule('ShopModule', $storeId)->naturalSearchShopFlatProductForHooks(
            $query,
            $lang,
            $start,
            $limit,
            $order,
            $filters
        );
    }

    /**
     * get list of Shop product assigns
     *
     * @param $storeId
     * @param int $start
     * @param int $limit
     * @param array $order
     * @param array $filters
     * @return mixed
     * @throws \Exception
     */
    public function listShopShopProductAssigns(
        $storeId,
        int $start,
        int $limit,
        array $order,
        array $filters
    ) {
        return $this->authHelper->getModule('ShopModule', $storeId)->listShopShopProductAssigns(
            $start,
            $limit,
            $order,
            $filters
        );
    }

    /**
     * Update stock
     *
     * @param $storeId
     * @param $storeKeeperId
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Validation\ValidationException
     */
    public function updateStock($storeId, $storeKeeperId)
    {
        $language = $this->authHelper->getLanguageForStore($storeId);

        $results = $this->authHelper->getModule('ShopModule', $storeId)->naturalSearchShopFlatProductForHooks(
            ' ',
            $language,
            0,
            1,
            [],
            [
                [
                    'name' => 'flat_product/product_id__=',
                    'val' => $storeKeeperId
                ]
            ]
        );

        if (isset($results['data']) && count($results['data']) > 0) {
            $result = $results['data'][0];
            $product_stock = $result['flat_product']['product']['product_stock'];

            if ($product = $this->exists($storeId, $result)) {
                $this->updateProductStock($storeId, $product, $product_stock);
            }
        } else {
            throw new \Exception("Product {$storeKeeperId} does not exist in StoreKeeper");
        }
    }

    /**
     * Update product stock
     *
     * @throws \Magento\Framework\Validation\ValidationException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Exception
     */
    private function updateProductStock($storeId, $product, $product_stock)
    {
        $stockItem = $this->stockRegistry->getStockItem($product->getId());
        if ($stockItem) {
            if ($stockItem->getManageStock()) {
                $stockItem->setData('is_in_stock', $product_stock['value'] > 0);
                $stockItem->setData('qty', $product_stock['value']);
                $stockItem->setData('use_config_notify_stock_qty', 1);
                $stockItem->save();

                $product->setStockData(
                    ['qty' => $product_stock['value'], 'is_in_stock' => $product_stock['value'] > 0]
                );
                $product->setQuantityAndStockStatus(
                    ['qty' => $product_stock['value'], 'is_in_stock' => $product_stock['value'] > 0]
                );
                $product->save();
            }
        // in some rare cases it can occur that a stock item doesn't exist in Magento 2
        // if there's no existing stock item, we'll create it
        } else {
            $stockItem->setData('is_in_stock', $product_stock['value'] > 0);
            $stockItem->setData('qty', $product_stock['value']);
            $stockItem->setData('manage_stock', true);
            $stockItem->setData('use_config_notify_stock_qty', 1);
            $stockItem->save();

            $product->setStockData(['qty' => $product_stock['value'], 'is_in_stock' => $product_stock['value'] > 0]);
            $product->setQuantityAndStockStatus(
                ['qty' => $product_stock['value'], 'is_in_stock' => $product_stock['value'] > 0]
            );
            $product->save();
        }
    }

    /**
     * Update product by Id
     *
     * @param $storeId
     * @param $storeKeeperId
     * @return void
     * @throws \Exception
     */
    public function updateById($storeId, $storeKeeperId)
    {
        $language = $this->authHelper->getLanguageForStore($storeId);

        $results = $this->authHelper->getModule('ShopModule', $storeId)->naturalSearchShopFlatProductForHooks(
            ' ',
            $language,
            0,
            1,
            [],
            [
                [
                    'name' => 'flat_product/product_id__=',
                    'val' => $storeKeeperId
                ]
            ]
        );

        if (isset($results['data']) && count($results['data']) > 0) {
            $result = $results['data'][0];
            if ($product = $this->exists($storeId, $result)) {
                $this->update($storeId, $product, $result);
            } else {
                $this->onCreate($storeId, $result);
            }
        } else {
            throw new \Exception("Product {$storeKeeperId} does not exist in StoreKeeper");
        }
    }

    /**
     * Update product links
     *
     * @param $storeId
     * @param $target
     * @param $storeKeeperEndpoint
     * @param $linkType
     * @return bool
     * @throws \Exception
     */
    public function updateProductLinks(
        $storeId,
        $target,
        $storeKeeperEndpoint = 'getUpsellShopProductIds',
        $linkType = 'upsell'
    )
    {
        $storekeeperProductId = $target->getStorekeeperProductId();
        if (empty($storeKeeperProductId)) {
            $storekeeperProductId = $target->getData()['storekeeper_product_id'];
        }

        if (empty($storekeeperProductId)) {
            throw new \Exception("Missing 'storekeeper_product_id' for {$target->getSku()}");
        }
        $storekeeperLinkIds = $this->authHelper->getModule('ShopModule', $storeId)
            ->$storeKeeperEndpoint($storekeeperProductId);

        $storekeeperLinkSkus = [];

        foreach ($storekeeperLinkIds as $storekeeperLinkId) {
            if ($linkedProduct = $this->exists($storeId, ['product_id' => $storekeeperLinkId])) {
                $storekeeperLinkSkus[] = $linkedProduct->getSku();
            }
        }

        $filtered = array_filter($target->getProductLinks(), function ($link) use ($linkType) {
            return $link->getLinkType() == $linkType;
        });

        $currentLinkSkus = array_map(function ($link) {
            return $link->getLinkedProductSku();
        }, $filtered);

        ksort($storekeeperLinkSkus);
        ksort($currentLinkSkus);

        if ($storekeeperLinkSkus !== $currentLinkSkus) {
            // filter all upsell
            $linkData = array_filter($target->getProductLinks(), function ($link) use ($linkType) {
                return $link->getLinkType() != $linkType;
            });

            foreach ($storekeeperLinkSkus as $index => $storekeeperLinkSku) {
                $productLink = $this->productLinkFactory->create();
                $linkData[] = $productLink->setSku($target->getSku())
                    ->setLinkedProductSku($storekeeperLinkSku)
                    ->setPosition($index)
                    ->setLinkType($linkType);
            }

            $target->setProductLinks($linkData);

            return true;
        }

        return false;
    }

    /**
     * If product exist
     *
     * @param $storeId
     * @param array $result
     * @return false|\Magento\Catalog\Api\Data\ProductInterface|\Magento\Framework\DataObject
     */
    public function exists($storeId, array $result)
    {
        $storekeeper_id = $this->getResultStoreKeeperId($result);
        try {
            $collection = $this->productCollectionFactory->create();
            $collection
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('storekeeper_product_id', $storekeeper_id)
                ->setFlag('has_stock_status_filter', false);

            if (is_array($result) && isset($result['flat_product']) && isset($result['flat_product']['product'])) {
                $collection->addAttributeToFilter(
                    'sku', $result['flat_product']['product']['sku'] ?? null
                );
            }

            if ($collection->count()) {
                $firstItem = $collection->getFirstItem();
                return $firstItem;
            }

            try {
                $storekeeper_sku = $this->getResultSku($result);

                if ($result = $this->productRepository->get($storekeeper_sku)) {
                    return $result;
                }
            } catch (\Exception $e) {
                // ignoring
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return false;
    }

    /**
     * If categories exist
     *
     * @param $storekeeper_category_ids
     * @return false|\Magento\Framework\DataObject[]|void
     * @throws LocalizedException
     */
    public function categoriesExist($storekeeper_category_ids)
    {
        if (count($storekeeper_category_ids) > 0) {
            $collection = $this->categoryCollectionFactory->create();
            $collection
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('storekeeper_category_id', ['in', $storekeeper_category_ids])
                ->setFlag('has_stock_status_filter', false);

            if ($collection->count() > 0) {
                return $collection->getItems();
            }

            return false;
        }
    }

    /**
     * Get Store WebsiteId
     *
     * @param $storeId
     * @return int|mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getStoreWebsiteId($storeId)
    {
        if (!isset($this->websiteIds[$storeId])) {
            $store = $this->storeManager->getStore($storeId);
            $this->websiteIds[$storeId] = $store->getWebsiteId();
        }

        return $this->websiteIds[$storeId];
    }

    /**
     * On product create
     *
     * @param $storeId
     * @param array $result
     * @return string|null
     */
    public function onCreate($storeId, array $result)
    {
        return $this->update($storeId, null, $result);
    }

    /**
     * On product deactivate
     *
     * @param $storeId
     * @param $targetId
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function onDeactivate($storeId, $targetId)
    {
        $websiteId = $this->getStoreWebsiteId($storeId);

        if ($target = $this->exists($storeId, [
            'product_id' => $targetId
        ])) {
            if (in_array($websiteId, $target->getWebsiteIds())) {
                $target->setWebsiteIds(array_diff($target->getWebsiteIds(), [$websiteId]));
                $this->productRepository->save($target);
            }
        } else {
            $this->updateById($storeId, $targetId);
        }
    }

    /**
     * On product activate
     *
     * @param $storeId
     * @param $targetId
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function activate($storeId, $targetId)
    {
        $websiteId = $this->getStoreWebsiteId($storeId);
        if ($target = $this->exists($storeId, [
            'product_id' => $targetId
        ])) {
            $websiteIds = $target->getWebsiteIds();
            if (!in_array($websiteId, $websiteIds)) {
                $websiteIds[] = $websiteId;
                $target->setWebsiteIds($websiteIds);
                $target = $this->productRepository->save($target);
            }
        } else {
            $this->updateById($websiteId, $targetId);
        }
    }

    /**
     * Update product
     *
     * @param $storeId
     * @param $target
     * @param array $result
     * @return string|void
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function update($storeId, $target = null, array $result = [])
    {
        $this->storeManager->setCurrentStore($storeId);

        $language = $this->authHelper->getLanguageForStore($storeId);

        $websiteId = $this->getStoreWebsiteId($storeId);

        $flat_product = $result['flat_product'];
        $product_price = $result['product_price'];

        $price = $product_price['ppu'];
        $price_wt = $product_price['ppu_wt'];

        $title = $flat_product['title'];

        $summary = $flat_product['summary'] ?? '';
        $body = $flat_product['body'];
        $slug = $flat_product['slug'];

        $product = $flat_product['product'];

        $sku = $product['sku'];

        $type = $product['type'];
        $shouldUpdate = false;

        if ($type == 'simple') {
            $update = !is_null($target);
            $create = !$update;

            if ($update) {
                $target = $this->productFactory->create()->load($target->getId());
            } else {
                $shouldUpdate = true;
                $target = $this->productFactory->create();
            }

            if ($language == " ") {
                $target->setStoreId(Store::DEFAULT_STORE_ID);
            }

            $newStatus = $product['active'] ?
                Status::STATUS_ENABLED :
                Status::STATUS_DISABLED;

            if ($target->getStatus() != $newStatus) {
                $target->setStatus($newStatus);
            }

            if ($create) {
                $target->setAttributeSetId(4); // default
                $target->setSku($sku);
            }

            if ($target->getName() != $title) {
                $shouldUpdate = true;
                $target->setName($title);
            }

            if ((float) $target->getPrice() != (float) $price_wt) {
                $shouldUpdate = true;
                $target->setPrice($price_wt);
            }

            $storekeeper_id = $this->getResultStoreKeeperId($result);

            if ($target->getStoreKeeperProductId() != $storekeeper_id) {
                $target->setStorekeeperProductId($storekeeper_id);
            }

            $seo_title = $flat_product['seo_title'] ?? null;
            if ($target->getMetaTitle() != $seo_title) {
                $shouldUpdate = true;
                $target->setMetaTitle($seo_title);
            }

            $seo_description = $flat_product['seo_description'] ?? null;
            if ($target->getMetaDescription() != $seo_description) {
                $shouldUpdate = true;
                $target->setMetaDescription($seo_description);
            }

            if ($target->getUrlKey() != $slug) {
                $shouldUpdate = true;
                $target->setUrlKey($slug);
            }

            $parseDown = new Parsedown();

            $newDescription = $parseDown->text($body);
            if ($target->getDescription() != $newDescription) {
                $shouldUpdate = true;
                $target->setDescription($newDescription);
            }

            $newShortDescription = $parseDown->text($summary);
            if ($target->getShortDescription() != $newShortDescription) {
                $shouldUpdate = true;
                $target->setShortDescription($newShortDescription);
            }

            $websiteId = $this->getStoreWebsiteId($storeId);
            $websiteIds = $target->getWebsiteIds();

            if (!in_array($websiteId, $websiteIds)) {
                $shouldUpdate = true;
                $websiteIds[] = $websiteId;
                $target->setWebsiteIds($websiteIds);
            }

            if (isset($flat_product['main_image'])) {
                $existingImage = null;
                $newImagePath = explode(
                    '/',
                    parse_url($flat_product['main_image']['big_url'], PHP_URL_PATH)
                );
                $newImageName = pathinfo(end($newImagePath), PATHINFO_FILENAME);

                if ($target->getImage()) {
                    $existingImagePath = explode('/', $target->getImage());
                    $existingImage = pathinfo(end($existingImagePath), PATHINFO_FILENAME);
                }

                if ($existingImage) {
                    if ($existingImage == 'no_selection'
                        || !preg_match("/^{$newImageName}\_[0-9]+/", $existingImage)) {
                        $shouldUpdate = true;
                        $this->setGalleryImage($flat_product['main_image']['big_url'], $target, true);
                    }
                }
            }

            $galleryImages = $target->getMediaGalleryImages()->getItems();
            $existingImagesArray = [];
            foreach ($galleryImages as $image) {
                $imagePath = explode('/', parse_url($image->getFile(), PHP_URL_PATH));
                $imageName = pathinfo(end($imagePath), PATHINFO_FILENAME);
                $existingImagesArray[] = $imageName;
            }

            if (isset($flat_product['product_images'])) {
                $mainImage = explode('/', $flat_product['main_image']['big_url']);
                $mainImageName = pathinfo(end($mainImage), PATHINFO_FILENAME);
                foreach ($flat_product['product_images'] as $product_image) {
                    $newImagePath = explode('/', parse_url($product_image['big_url'], PHP_URL_PATH));
                    $newImageName = pathinfo(end($newImagePath), PATHINFO_FILENAME);

                    $countDuplicates = count(preg_grep("/^{$newImageName}\_[0-9]+/", $existingImagesArray));
                    $shouldUpdate = true;

                    if ($newImageName
                        !== $mainImageName
                        && !preg_match("/^{$newImageName}\_[0-9]+/", $mainImageName)) {
                        if (!in_array($newImageName, $existingImagesArray) && $countDuplicates <= 0) {
                            $this->setGalleryImage($product_image['big_url'], $target, false);
                        }
                    }
                }
            }

            if (
                $this->updateProductLinks(
                    $storeId, $target,
                    'getUpsellShopProductIds',
                    'upsell')
            ) {
                $shouldUpdate = true;
            }

            if ($this->updateProductLinks(
                $storeId,
                $target,
                'getCrossSellShopProductIds',
                'crosssell')
            ) {
                $shouldUpdate = true;
            }

            if ($shouldUpdate) {
                $this->productRepository->save($target);

                if ($update) {
                    if ($language == ' ') {
                        $this->setProductToUseDefaultValues($target, $storeId);
                    }
                }
            }

            $shouldUpdateStock = false;
            $stockItem = $this->stockItem->load($target->getId(), 'product_id');

            (float) $product_stock_value = $product['product_stock']['value'];
            $product_stock_in_stock = $product['product_stock']['in_stock'];
            $product_stock_unlimited = $product['product_stock']['unlimited'];

            if ($product_stock_unlimited &&
                ($stockItem->GetBackorders() == false || $stockItem->getUseConfigBackOrders() == true)
            ) {
                $stockItem->setBackorders(true);
                $stockItem->setUseConfigBackorders(false);
            } elseif (!$product_stock_unlimited &&
                ($stockItem->GetBackorders() == true || $stockItem->getUseConfigBackOrders() == false)
            ) {
                $stockItem->setBackorders(false);
                $stockItem->setUseConfigBackorders(true);
            }

            // prevent managing stock for storekeeper products
            if ($stockItem->getManageStock() || $stockItem->getUseConfigManageStock()) {
                $shouldUpdateStock = true;
                $stockItem->setManageStock(false);
                $stockItem->setUseConfigManageStock(false);
            }

            if (is_null($stockItem->getQty()) || $stockItem->getQty() != $product_stock_value) {
                $shouldUpdateStock = true;
                $stockItem->setQty($product_stock_value);
            }

            if ($shouldUpdateStock) {
                $stockItem->save();
            }

            if (!empty($categoryIds = $this->getResultCategoryIds($result))) {
                // assign the store to the first available, otherwise delete operations will go wrong

                // check if categories exist
                if ($categories = $this->categoriesExist($categoryIds)) {
                    $categoryIds = array_map(function ($category) {
                        return $category->getId();
                    }, $categories);

                    try {
                        $diff = array_diff($target->getCategoryIds(), $categoryIds);
                        if (empty($target->getCategoryIds()) || $diff) {
                            if ($diff) {
                                foreach ($diff as $categoryId) {
                                    $this->categoryLinkRepository->deleteByIds($categoryId, $target->getSku());
                                }
                            }

                            $this->categoryLinkManagement->assignProductToCategories(
                                $target->getSku(),
                                $categoryIds
                            );
                        }
                    } catch (\Exception $e) {
                        $this->logger->error($e->getMessage());
                    }
                }
            }
        } else {
            return "Skipping product type {$type} with sku {$sku}\n";
        }
    }

    /**
     * Set gallery image
     *
     * @param $image
     * @param $target Product
     * @param $mainImage
     */
    private function setGalleryImage($image, Product $target, $mainImage)
    {
        $tmpDir = $this->getMediaTmpDir();
        $url = self::API_URL . parse_url($image, PHP_URL_PATH);
        $newImage = $tmpDir . baseName($url);
        $result = $this->file->read($url, $newImage);
        $imageTypes = [];

        if ($mainImage) {
            $imageTypes = ['image', 'small_image', 'thumbnail'];
        }

        if ($result) {
            try {
                $target->addImageToMediaGallery($newImage, $imageTypes, true, false);
            } catch (LocalizedException $e) {
                $this->logger->error($exception->getMessage());
            }
        }
    }

    /**
     * Get media temp directory path
     *
     * @return string
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function getMediaTmpDir()
    {
        $tmpDir = $this->directoryList->getPath(DirectoryList::MEDIA) . DIRECTORY_SEPARATOR . 'import/';
        $this->file->checkAndCreateFolder($tmpDir);

        return $tmpDir;
    }

    /**
     * Get result StoreKeeperId
     *
     * @param $result
     * @return mixed
     */
    private function getResultStoreKeeperId($result)
    {
        return $result['product_id'];
    }

    /**
     * Get result sku
     *
     * @param $result
     * @return mixed
     */
    private function getResultSku($result)
    {
        return $result['flat_product']['product']['sku'];
    }


    /**
     * Get result category Ids
     *
     * @param $result
     * @return array
     */
    private function getResultCategoryIds($result)
    {
        return array_map(function ($cat) {
            return $cat['id'];
        }, $result['flat_product']['categories'] ?? []);
    }

    /**
     * Set product to use Default Values
     *
     * @param $target
     * @param $storeId
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    private function setProductToUseDefaultValues($target, $storeId)
    {
        $target->setStoreId($storeId);
        $productData = $target->getData();
        $productData['name'] = null;
        $productData['description'] = false;
        $productData['short_description'] = false;
        $productData['meta_title'] = false;
        $productData['meta_description'] = false;
        $productData['url_key'] = false;

        $target->setData($productData);
        $this->productRepository->save($target);
    }
}
