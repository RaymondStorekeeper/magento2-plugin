<?php

namespace StoreKeeper\StoreKeeper\Console\Command\Sync;

use StoreKeeper\ApiWrapper\ApiWrapper;
use StoreKeeper\ApiWrapper\Wrapper\FullJsonAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SomeCommand
 */
class Categories extends Command
{
    const STORES = 'stores';

    public $phpsessid = null;

    public function __construct(
        \Magento\Framework\App\State $state,
        \StoreKeeper\StoreKeeper\Helper\Api\Categories $categoriesHelper
    ) {
        parent::__construct();

        $this->state = $state;
        $this->categoriesHelper = $categoriesHelper;
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName("storekeeper:sync:categories");
        $options = [
            new InputOption(
                self::STORES,
                null,
                InputOption::VALUE_REQUIRED,
                'Store IDs'
            )
        ];
        $this->setDescription('The Store IDs');
        $this->setDefinition($options);

        parent::configure();
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return null|int
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

        $storeId = 1;

        $current = 0;
        $total = null;

        // $this->categoriesHelper->updateById(1, 8);
        // die;

        while (is_null($total) || $current < $total) {
            $response = $this->categoriesHelper->listCategories(
                $storeId,
                $current,
                2,
                [
                    [
                        "name" => "category_tree/path",
                        "dir" => "asc"
                    ]
                ],
                []
            );

            echo "\nProcessing " . ($current + $response['count']) . ' out of ' . $response['total'] . " results\n\n";

            $total = $response['total'];
            $current += $response['count'];

            $results = $response['data'];

            foreach ($results as $result) {
                if ($category = $this->categoriesHelper->exists($storeId, $result)) {
                    $category = $this->categoriesHelper->update($storeId, $category, $result);
                } else {
                    $category = $this->categoriesHelper->create($storeId, $result);
                }
            }
        }
    }
}