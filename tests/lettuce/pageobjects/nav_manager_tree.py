from time import sleep
from terrain.action_chains import ActionChains

class NavManTree:                 
    """Page Object for the nav manager tree."""
    
    def __init__(self, webdriver):
        self.__driver = webdriver
    
    def get_node_from_id(self, id):
        """Return the node associated with a post ID"""
        return self.__driver.find_element_by_id('nm' + str(id))

    def get_node_from_title(self, title):
        """Return the node associated with a post title"""
        for li in self.__driver.find_elements_by_tag_name('li'):
            id = li.get_attribute('id')
            if str(li.get_attribute('id')).startswith('nm'):
                node_title = li.find_element_by_class_name("title").text
                if (node_title == title):
                    return li
    
    def get_id_from_node(self, node):
        # strip off 'nm' prefix
        return node.get_attribute('id')[2:];
    
    def drag_node(self, handle, drop, xoffset = 0, yoffset = 0, delay = 0):
        """Drag a node from handle to drop's center, with an additional x and y
        offset to the destination.  delay adds a pause before releasing the
        mouse; useful for debugging.  The move_by_offset's seem to help the
        browser to recognize that a move was made."""
        ActionChains(self.__driver).\
            click_and_hold(handle).\
            move_to_element_with_offset(drop, xoffset, yoffset).\
            move_by_offset(1, 0).\
            move_by_offset(1, 0).\
            move_by_offset(1, 0).\
            sleep(delay).\
            release().\
            perform()
        return self
    
    def move_after(self, source, target, delay = 0):
        """Move source after target."""
        target = target.find_element_by_tag_name('a')
        self.drag_node(
            source.find_element_by_tag_name('a'),
            target,
            delay = delay,
            yoffset = target.size['height'])
        return self
    
    def move_into(self, source, target, delay = 0):
        """Move source into target as the first child."""
        target = target.find_element_by_tag_name('a')
        self.drag_node(
            source.find_element_by_tag_name('a'),
            target,
            delay = delay,
            yoffset = target.size['height'] / 2)
        return self
    
    def move_before(self, source, target, delay = 0):
        """Move source before target."""
        target = target.find_element_by_tag_name('a')
        self.drag_node(
			source.find_element_by_tag_name('a'),
            target,
            delay = delay,
            )
        return self
        
    def toggle_node(self, node):
        """Toggle a node collapsed/expanded"""
        if not 'jstree-leaf' in node.get_attribute('class'):
            node.find_element_by_tag_name('ins').click()
        return self
    
    def collapse_node(self, node):
        """Collapse an expanded node"""
        if 'jstree-open' in node.get_attribute('class'):
            self.toggle_node(node)
        return self
    
    def expand_node(self, node):
        """Expand a collapsed node"""
        if 'jstree-closed' in node.get_attribute('class'):
            self.toggle_node(node)
        return self
    
    def expand_through(self, nodes):
        """Expand each node in nodes, in order"""
        for node in nodes:
            self.expand_node(node)
        return self
        
    def expand_to(self, node):
        """Expand as necessary to make node visible."""
        if not node.is_displayed():
            ancestors = node.find_elements_by_xpath('ancestor::li')
            self.expand_through(ancestors)
        return self
