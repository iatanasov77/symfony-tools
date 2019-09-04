<?php namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

use App\Service\TwigService;

class CreateVirtualHostCommand extends Command
{
    protected static $defaultName = 'vs:mkvhost';
    
    private $twig;
    
    public function __construct( TwigService $twig )
    {
        $this->twig = $twig;
        
        parent::__construct();
    }
    
    protected function configure()
    {
        $this
            ->setDescription( 'Creates a virtual host.' )
            ->setHelp( '"Usage: php bin/console vs:mkvhost -t vhost_template -s your_domain.com -d /path/to/document_root";' )
        ;
        
        $this
            ->addOption( 'template', 't', InputOption::VALUE_OPTIONAL, 'Select a template for the virtual host configuration', 'simple' )
            ->addOption( 'host', 's', InputOption::VALUE_OPTIONAL, 'Select a host address for the server', 'example.com' )
            ->addOption( 'documentroot', 'd', InputOption::VALUE_OPTIONAL, 'Select document root path for this virtual host', '/var/www/html' )
            ->addOption( 'with-ssl', null, InputOption::VALUE_NONE )
        ;
    }
    
    protected function execute( InputInterface $input, OutputInterface $output )
    {
        posix_getuid() === 0 || die( "You must to be root.\n" );
        
        $ip = '127.0.0.1';
        $template   = 'templates/mkvhost/' . $input->getOption( 'template' ) . '.twig';
        $host   = $input->getOption( 'host' );
        $documentRoot   = $input->getOption( 'documentroot' );
        $serverAdmin    = 'admin@' . $host;
        $vhostConfFile	= '/etc/apache2/sites-available/' . $host . '.conf';
        $apacheLogDir   = '/var/log/apache2/';
        $withSsl        = $input->getOption( 'with-ssl' );
        
        $output->writeln([
            'VS Virtual Host Creator',
            '=======================',
            '',
        ]);
        
        // Create Virtual Host
        $output->writeln( 'Creating virtual host...' );
        $vhost  = $this->twig->render( $template, [
            'host' => $host,
            'documentRoot' => $documentRoot,
            'serverAdmin' => $serverAdmin,
            'apacheLogDir' =>$apacheLogDir
        ]);
        if ( $withSsl )
        {
            $vhost  .= "\n\n" . $this->twig->render( 'templates/mkvhost/ssl.twig', [
                'host' => $host,
                'documentRoot' => $documentRoot,
                'serverAdmin' => $serverAdmin,
                'apacheLogDir' =>$apacheLogDir
            ]);
        }
        file_put_contents( $vhostConfFile, $vhost );
        exec( "a2ensite {$host}" );
        
        // Reload Apache
        $output->writeln( 'Restarting apache service...' );
        exec( "service apache2 restart" );
        
        // Create a /etc/hosts record
        $output->writeln( 'Creating a /etc/hosts record...' );
        $hosts = file_get_contents('/etc/hosts');
        if( stripos( $host, $hosts ) === FALSE )
        {
            file_put_contents( '/etc/hosts', sprintf( "%s\t%s www.%s\n", $ip, $host, $host ), FILE_APPEND );
        }
        
        $output->writeln( 'Virtual host created successfully!' );
    }
}
