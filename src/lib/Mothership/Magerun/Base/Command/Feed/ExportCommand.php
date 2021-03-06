<?php
/**
 * This file is part of the Mothership GmbH code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Mothership\Magerun\Base\Command\Feed;

use Symfony\Component\Console\Input\InputOption;
use Mothership\Component\Feed\Output\OutputCsv;

use \Mothership\Magerun\Base\Command\AbstractMagentoCommand;

/**
 * Class ExportCommand
 *
 */
class ExportCommand extends AbstractMagentoCommand
{
    protected $description = 'Run the feed generation.';

    /**
     * Command config
     *
     * @return void
     */
    protected function configure()
    {
        parent::configure();
        $this->addOption(
            'config',
            'c',
            InputOption::VALUE_REQUIRED,
            'The name of the configuration'
        );

        $this->addOption(
            'separator',
            's',
            InputOption::VALUE_REQUIRED,
            'CSV Separator',
            ','
        );
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int|void
     */
    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {

        parent::execute($input, $output);

        $input_path  = $this->getApplication()->getMagentoRootFolder() . '/app/etc/mothership/feeds';
        $output_path = $this->getApplication()->getMagentoRootFolder() . '/media/feeds/';

        $filename = $this->_detectConfiguration($input, $output, $input_path);

        /**
         * Initialize the database settings and store them in a seperate array
         * which is used to overwrite the database configuration in the feed configuration file later
         */
        $this->detectDbSettings($output);

        if (!file_exists($input_path . '/' . $filename)) {
            throw new \Exception('File ' . $input_path . '/' . $filename . ' does not exist');
        }

        $input_interface = new \Mothership\Component\Feed\Input\InputMysqlData($this->getConnection());

        $factory = new \Mothership\Magerun\Base\Feed\FeedFactory($input_path . '/' . $filename, $input_interface);
        $factory->processFeed(new OutputCsv($output_path . $filename . '.csv', $input->getOption('separator')));

        // This is my changed code

        $output->writeln('<comment>File saved to : ' . $output_path . $filename . '.csv' . '</comment>');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param string                                            $path
     *
     * @return string
     */
    protected function _detectConfiguration(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output,
        $path
    ) {
        /**
         * If the user sets the option environment variable, then try to find it.
         */
        if ($input->getOption('config')) {
            $file_name = $input->getOption('config');
            $full_path = $path . DIRECTORY_SEPARATOR . $input->getOption('config');
            $output->writeln('<info>Option "' . $input->getOption('config') . '" set</info>');
            if (!file_exists($full_path)) {
                $output->writeln('<comment>Configuration-File ' . $full_path . ' not found. .</comment>');
            } else {
                $output->writeln('<info>Configuration-File ' . $full_path . ' found</info>');
            }
        } else {
            $output->writeln('<info>Scanning folder ' . $path . ' for configuration files</info>');

            $environment_files = [];
            foreach (glob($path . DIRECTORY_SEPARATOR . '*.yaml') as $_file) {
                $_part               = pathinfo($_file);
                $environment_files[] = $_part['basename'];
            }

            $dialog      = $this->getHelper('dialog');
            $environment = $dialog->select(
                $output,
                'Please select your feed configuration',
                $environment_files,
                0
            );
            $output->writeln('You have just selected: ' . $environment_files[$environment]);
            $file_name = $environment_files[$environment];
        }

        return $file_name;
    }
}
