<?php

/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace AppserverIo\Console\Server\Doctrine\DBAL\Migrations\Tools\Console\Command;

use Doctrine\DBAL\Migrations\Configuration\Configuration;
use Doctrine\DBAL\Migrations\Tools\Console\Command\AbstractCommand;
use Doctrine\DBAL\Migrations\Tools\Console\Helper\MigrationDirectoryHelper;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for generating new blank migration classes
 *
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link    www.doctrine-project.org
 * @since   2.0
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Tim Wagner <tw@appserver.io>
 */
class GenerateCommand extends AbstractCommand
{

    private static $_template =
        '<?php
namespace <namespace>;

use Doctrine\DBAL\Schema\Schema;
use AppserverIo\Psr\EnterpriseBeans\Annotations as EPB;
use AppserverIo\Console\Server\Doctrine\DBAL\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 *
 * @EPB\Migration
 */
class Version<version> extends AbstractMigration
{

    /**
     * Updates the passed schema.
     *
     * @param \Doctrine\DBAL\Schema\Schema $schema The schema to update
     *
     * @return void
     */
    public function up(Schema $schema)
    {
<up>
    }
    
    /**
     * Downgrades the passed schema.
     *
     * @param \Doctrine\DBAL\Schema\Schema $schema The schema to downgrade
     *
     * @return void
     */
    public function down(Schema $schema)
    {
<down>
    }
    
    /**
     * Invoked after the passed schema has been updated.
     *
     * Use this method if you want to upgrade application data.
     *
     * @param \Doctrine\DBAL\Schema\Schema $schema The schema
     *
     * @return void
     * @throws \Doctrine\DBAL\Migrations\AbortMigrationException
     */
    public function preUp(Schema $schema)
    {
<abortCondition>
    }
    
    /**
     * Invoked after the passed schema has been downgraded.
     *
     * Use this method if you want to downgrade application data.
     *
     * @param \Doctrine\DBAL\Schema\Schema $schema The schema
     *
     * @return void
     * @throws \Doctrine\DBAL\Migrations\AbortMigrationException
     */
    public function preDown(Schema $schema)
    {
<abortCondition>
    }
}
';

    protected function configure()
    {
        $this
                ->setName('migrations:generate')
                ->setDescription('Generate a blank migration class.')
                ->addOption('editor-cmd', null, InputOption::VALUE_OPTIONAL, 'Open file with this command upon creation.')
                ->setHelp(<<<EOT
The <info>%command.name%</info> command generates a blank migration class:

    <info>%command.full_name%</info>

You can optionally specify a <comment>--editor-cmd</comment> option to open the generated file in your favorite editor:

    <info>%command.full_name% --editor-cmd=mate</info>
EOT
        );

        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = $this->getMigrationConfiguration($input, $output);

        $version = $configuration->generateVersionNumber();
        $path = $this->generateMigration($configuration, $input, $version);

        $output->writeln(sprintf('Generated new migration class to "<info>%s</info>"', $path));
    }

    protected function getTemplate()
    {
        return self::$_template;
    }

    protected function generateMigration(Configuration $configuration, InputInterface $input, $version, $up = null, $down = null)
    {
        $currentPlatform = $configuration->getConnection()->getDatabasePlatform()->getName();
        $abortCondition = sprintf(
            "\$this->abortIf(\$this->connection->getDatabasePlatform()->getName() !== %s, %s);",
            var_export($currentPlatform, true),
            var_export(sprintf("Migration can only be executed safely on '%s'.", $currentPlatform), true)
        );

        $placeHolders = [
            '<namespace>',
            '<version>',
            '<up>',
            '<down>',
            '<abortCondition>'
        ];
        $replacements = [
            $configuration->getMigrationsNamespace(),
            $version,
            $up ? "        " . implode("\n        ", explode("\n", $up)) : null,
            $down ? "        " . implode("\n        ", explode("\n", $down)) : null,
            $abortCondition ? "        " . implode("\n        ", explode("\n", $abortCondition)) : null
        ];
        $code = str_replace($placeHolders, $replacements, $this->getTemplate());
        $code = preg_replace('/^ +$/m', '', $code);
        $migrationDirectoryHelper = new MigrationDirectoryHelper($configuration);
        $dir = $migrationDirectoryHelper->getMigrationDirectory();
        $path = $dir . '/Version' . $version . '.php';

        file_put_contents($path, $code);

        if ($editorCmd = $input->getOption('editor-cmd')) {
            proc_open($editorCmd . ' ' . escapeshellarg($path), [], $pipes);
        }

        return $path;
    }
}
