from mako.template import Template
from mako.lookup import TemplateLookup

mylookup = TemplateLookup(directories=['.'])
rawtemplate = Template(filename='constitution-raw.txt')
mytemplate = Template(filename='constitution-template.txt', lookup=mylookup)
print mytemplate.render()
