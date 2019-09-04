<?php namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class BumpVersionCommand extends Command
{
    protected static $defaultName = 'vs:bumpversion';
    
    protected function configure()
    {
        $this
            ->setDescription( 'Bump the release version.' )
            ->setHelp( 'Bump the version and add CHANGES info from commit messages.' )
        ;
    }
    
    protected function execute( InputInterface $input, OutputInterface $output )
    {
        $versionFile		= 'VERSION';
        $changesFile		= 'CHANGES';
        $tempChangesFile	= 'TMP_CHANGES';
        $editor				= '/usr/bin/vim';
        $changesPrefix		= '* ';
        $initialVersion		= '0.0.0';
        
        if( !file_exists( $versionFile ) )
        {
            file_put_contents( $versionFile, $initialVersion );
        }
        $lastVersion		= file_get_contents( $versionFile );
        printf( "Current version : %s\n", $lastVersion );
        list( $versionMajor, $versionMinor, $versionPatch )	= explode('.', $lastVersion);
        
        $versionMinor++;
        $versionPatch		= 0;
        $suggestedVersion	= sprintf( "%d.%d.%d", $versionMajor, $versionMinor, $versionPatch );
        printf( "Enter a version number [%s]: ", $suggestedVersion );
        $input				= trim( fgets( STDIN ) );
        if( ! empty( $input ) )
            $suggestedVersion	= $input;
        
        // Fetch CHANGES , edit its and append to CHAGES file
        $gitLogCommand		= ( $lastVersion === $initialVersion )
                                ? sprintf( 'git log --pretty=format:" %s%%s"', $changesPrefix )
                                : sprintf( 'git log --pretty=format:" %s%%s"  %s...HEAD', $changesPrefix, $lastVersion );
        $changes			= sprintf(
            "%s:\n-----\nRelease date: **%s**\n\n%s\n\n",
            $suggestedVersion,
            date( "d.m.Y" ),
            shell_exec( $gitLogCommand )
        );
        file_put_contents( $tempChangesFile, $changes );
        // Run the editor
        $descriptors    = [
            ['file', '/dev/tty', 'r'],
            ['file', '/dev/tty', 'w'],
            ['file', '/dev/tty', 'w']
        ];
        $process        = proc_open( "$editor $tempChangesFile", $descriptors, $pipes );
        while(true){
            if (proc_get_status($process)['running']==FALSE){
                break;
            }
        }
        
        $oldChanges			= file_exists( $changesFile ) ? file_get_contents( $changesFile ) : '';
        file_put_contents( $tempChangesFile, $oldChanges, FILE_APPEND );
        exec( "mv $tempChangesFile $changesFile" );
        file_put_contents( $versionFile, $suggestedVersion );
        // Commit VERSION and CHANGES files
        exec( sprintf( 'git add %s %s', basename( $versionFile ), basename( $changesFile ) ) );
        exec( sprintf( 'git commit -m "Version bump to %s"', $suggestedVersion ) );
    }
}
