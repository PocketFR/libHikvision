# libHikvision Fork
Add some stuff :
  - Easy to use with multiple cameras ( edit and add more files like CAM01.php )
  - Add support for new Hikvision cameras that use Sqlite instead old binary files
  - Add timeline
  - Add multiple selection to concatenate multiple sequences into one single video file
  - Add php cron file to erase video every 30 days to complain the French CNIL

# libHikvision
A PHP Class for working with Hikvision datadir's, this file will parse index00.bin files that Hikvision IP Camera software 
stores on SD cards and NFS Shares.

Using this class you can view details about recordings stored in a datadir and extract video and thumbnails.

An example application is included named 'CAM01.php' that require 'record-viewer.php'. Made sure to edit paths on this files to match with your system.

## Contributing

1. Fork it!
2. Create your feature branch: `git checkout -b my-new-feature`
3. Commit your changes: `git commit -am 'Add some feature'`
4. Push to the branch: `git push origin my-new-feature`
5. Submit a pull request :D


## Credits

Based on Alexey Ozerov's c++ code, available at https://github.com/aloz77/hiktools/


## License

GPL 2.0
