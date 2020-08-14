**THIS PROJECT HAS BEEN ARCHIVED**

Please see https://code.usgs.gov/ghsc/hazdev/hazdev-basemap

hazdev-basemap
==============

Tile overlays used in hazdev applications.


### Getting Started:

- Run the pre-install script
```src/lib/pre-install```, on Windows ```php src/lib/pre-install.php```

Download tile layers for example page to work.

- Run grunt

- Open http://localhost:8000/basemap.html to view layers


### Setting up locally via Docker container:
This docker image does not include tiles. The user must manually download them.
A user should start by making a directory for data downloads:

```
mkdir -p data/tiles
```

The user then downloads the data while within the data/tiles directory:
```
cd data/tiles
curl -O ftp://hazards.cr.usgs.gov/web/hazdev-basemap/faults.jsonp
curl -O ftp://hazards.cr.usgs.gov/web/hazdev-basemap/faults.mbtiles
curl -O ftp://hazards.cr.usgs.gov/web/hazdev-basemap/plates.jsonp
curl -O ftp://hazards.cr.usgs.gov/web/hazdev-basemap/plates.mbtiles
curl -O ftp://hazards.cr.usgs.gov/web/hazdev-basemap/ushaz.tar.gz
tar -xzvf ushaz.tar.gz
```

Build the docker image with a tag:

```
docker build -t usgs/hazdev-basemap:latest
```

Run the docker image with a volume mount specifying where to find these newly downloaded files:

```
docker run -v $(pwd)/data:/var/www/data/hazdev-basemap --rm -it -p 8000:80 usgs/hazdev-basemap:latest
```

View the tiles from the container in your browser with:

```
https://localhost:8000
```
