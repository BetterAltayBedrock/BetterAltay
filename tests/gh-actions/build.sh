VERSION="$1"

sudo apt update && sudo apt install -y \
	re2c \
	libtool \
	libtool-bin \
	zlib1g-dev \
	libcurl4-openssl-dev \
	libxml2-dev \
	libyaml-dev \
	libgmp-dev \
	libzip-dev \
	libssl-dev

INSTALL_DIR="$(pwd)/bin/php7"

export CFLAGS="$CFLAGS -march=x86-64"
export CXXFLAGS="$CXXFLAGS -march=x86-64"

git clone https://github.com/php-build/php-build.git
cd php-build
./install-dependencies.sh
echo '"pthreads",,"https://github.com/pmmp/ext-pmmpthread.git",,,"extension",' >> share/php-build/extension/definition

if [[ "$VERSION" == 8.2.* ]]; then
	#ext-pmmpthread 4.2.1 is the last tag exposing the legacy pthreads API (Threaded/Thread in the root namespace) and supports PHP 8.2
	EXTENSIONS='pthreads=@4.2.1 yaml=2.3.0'
else
	EXTENSIONS='pthreads=@a6afc0434f91c1e9541444aef6ac7a1f16c595be yaml=2.2.1'
fi

PHP_BUILD_INSTALL_EXTENSION="$EXTENSIONS" PHP_BUILD_ZTS_ENABLE=on ./bin/php-build "$VERSION" "$INSTALL_DIR" || exit 1
rm "$INSTALL_DIR/etc/conf.d/xdebug.ini" || true
