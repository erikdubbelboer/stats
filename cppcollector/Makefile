
all: jsoncpp/libs/linux-gcc-4.5.2/libjson_linux-gcc-4.5.2_libmt.so
	g++ -g cppcollector.cc -Wall -L/usr/local/lib/ -lhiredis -lboost_thread-mt -lpthread -lboost_system -l:jsoncpp/libs/linux-gcc-4.5.2/libjson_linux-gcc-4.5.2_libmt.so -o cppcollector

jsoncpp/libs/linux-gcc-4.5.2/libjson_linux-gcc-4.5.2_libmt.so: jsoncpp/include/json/json.h
	scons -C jsoncpp platform="linux-gcc"

