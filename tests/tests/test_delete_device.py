#!/usr/bin/python3

import sys
import logging
import os
import time
import json
import binascii
from lib import *

'''
Tests a successful gps data submission and device deletion.
'''

if __name__ == '__main__':
    file_name = os.path.basename(__file__)
    parse_config()

    device_name = __file__
    for i in range(10):

        # Submit data.
        iv = binascii.hexlify(os.urandom(16)).decode("utf-8")
        lat = binascii.hexlify(os.urandom(16)).decode("utf-8")
        lon = binascii.hexlify(os.urandom(16)).decode("utf-8")
        alt = binascii.hexlify(os.urandom(16)).decode("utf-8")
        speed = binascii.hexlify(os.urandom(16)).decode("utf-8")
        utctime = int(time.time()) - (10-i)
        gps_data = {"iv": iv,
                    "device_name": device_name,
                    "lat": lat,
                    "lon": lon,
                    "alt": alt,
                    "speed": speed,
                    "utctime": utctime}

        payload = {"user": Settings.username,
                   "password": Settings.password,
                   "gps_data": json.dumps([gps_data])}
        logging.debug("[%s] Submitting gps data." % file_name)
        request_result = send_post_request("/submit.php", payload, file_name)
        if request_result["code"] != ErrorCodes.NO_ERROR:
            logging.error("[%s] Service error code: %d."
                          % (file_name, request_result["code"]))
            logging.debug("[%s] Json response: %s"
                          % (file_name, request_result))
            sys.exit(1)

        # Get submitted data and check received data.
        payload = {"user": Settings.username,
                   "password": Settings.password}
        location = "/get.php?mode=last" \
                   + "&device=" \
                   + device_name
        logging.debug("[%s] Getting gps data." % file_name)
        request_result = send_post_request(location, payload, file_name)
        if request_result["code"] != ErrorCodes.NO_ERROR:
            logging.error("[%s] Service error code: %d."
                          % (file_name, request_result["code"]))
            logging.debug("[%s] Json response: %s"
                          % (file_name, request_result))
            sys.exit(1)
        gps_data_recv = request_result["data"][0]
        keys = ["iv", "lat", "lon", "alt", "speed", "device_name", "utctime"]
        for key in keys:
            if gps_data_recv[key] != gps_data[key]:
                logging.error("[%s] Key '%s' contains wrong data."
                              % (file_name, key))
                logging.debug("[%s] Key '%s' contains: %s"
                              % (file_name, key, str(gps_data[key])))
                logging.debug("[%s] Json response: %s"
                              % (file_name, request_result))
                sys.exit(1)

    # Delete device.
    payload = {"user": Settings.username,
               "password": Settings.password}
    location = "/delete.php?mode=device" \
               + "&device=" \
               + device_name
    logging.debug("[%s] Deleting gps device." % file_name)
    request_result = send_post_request(location, payload, file_name)
    if request_result["code"] != ErrorCodes.NO_ERROR:
        logging.error("[%s] Service error code: %d."
                      % (file_name, request_result["code"]))
        logging.debug("[%s] Json response: %s"
                      % (file_name, request_result))
        sys.exit(1)

    # Get submitted data and check received data.
    payload = {"user": Settings.username,
               "password": Settings.password}
    location = "/get.php?mode=last" \
               + "&device=" \
               + device_name
    logging.debug("[%s] Getting gps data." % file_name)
    request_result = send_post_request(location, payload, file_name)
    if request_result["code"] != ErrorCodes.NO_ERROR:
        logging.error("[%s] Service error code: %d."
                      % (file_name, request_result["code"]))
        logging.debug("[%s] Json response: %s"
                      % (file_name, request_result))
        sys.exit(1)
    if len(request_result["data"]) > 0:
        logging.error("[%s] Response contains data. "
                      % file_name
                      + "Deleting device failed.")
        logging.debug("[%s] Json response: %s"
                      % (file_name, request_result))
        sys.exit(1)