#
# nav_management.py
#
# Step definitions for changing page order and nav settings.
#

from lettuce import *
from nav_manager_tree import NavManTree

##
# Basic UI testing
##
@step('I arrange "([^"]*)" after "([^"]*)"')
def arrange_after(step, source, target):
    nm = NavManTree(world.browser)
    source = nm.get_node_from_title(source)
    target = nm.get_node_from_title(target)
    nm.move_after(source, target)
